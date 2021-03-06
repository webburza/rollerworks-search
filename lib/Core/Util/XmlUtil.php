<?php

declare(strict_types=1);

/*
 * This file is part of the RollerworksSearch package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Rollerworks\Component\Search\Util;

/**
 * XMLUtils is a bunch of utility methods to XML operations.
 *
 * This class contains static methods only and is not meant to be instantiated.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Martin Hasoň <martin.hason@gmail.com>
 *
 * @see https://raw.github.com/symfony/symfony/master/src/Symfony/Component/Config/Util/XmlUtils.php
 */
final class XmlUtil
{
    /**
     * Loads an XML file.
     *
     * @param string          $file             An XML file path
     * @param string|callable $schemaOrCallable An XSD schema file path or callable
     *
     * @throws \InvalidArgumentException When loading of XML file returns error
     *
     * @return \DOMDocument
     */
    public static function loadFile(string $file, $schemaOrCallable = null): \DOMDocument
    {
        return static::parseXml(
            file_get_contents($file),
            $schemaOrCallable,
            sprintf('The XML file "%s" is not valid.', $file)
        );
    }

    /**
     * Parses an XML document.
     *
     * @param string          $content          An XML document as string
     * @param string|callable $schemaOrCallable An XSD schema file path or callable
     * @param string          $defaultMessage
     *
     * @throws \InvalidArgumentException When loading of XML document returns an error
     *
     * @return \DOMDocument
     */
    public static function parseXml(string $content, $schemaOrCallable = null, string $defaultMessage = 'The XML file is not valid.'): \DOMDocument
    {
        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $dom->validateOnParse = true;

        if (!$dom->loadXML($content, LIBXML_NONET | (defined('LIBXML_COMPACT') ? LIBXML_COMPACT : 0))) {
            libxml_disable_entity_loader($disableEntities);

            throw new \InvalidArgumentException(
                implode("\n", static::getXmlErrors($internalErrors))
            );
        }

        $dom->normalizeDocument();

        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($disableEntities);

        foreach ($dom->childNodes as $child) {
            if ($child->nodeType === XML_DOCUMENT_TYPE_NODE) {
                throw new \InvalidArgumentException('Document types are not allowed.');
            }
        }

        if (null !== $schemaOrCallable) {
            $internalErrors = libxml_use_internal_errors(true);
            libxml_clear_errors();

            $e = null;

            if (is_callable($schemaOrCallable)) {
                try {
                    $valid = call_user_func($schemaOrCallable, $dom, $internalErrors);
                } catch (\Exception $e) {
                    $valid = false;
                }
            } elseif (!is_array($schemaOrCallable) && is_file($schemaOrCallable)) {
                $valid = @$dom->schemaValidate($schemaOrCallable);
            } else {
                libxml_use_internal_errors($internalErrors);

                throw new \InvalidArgumentException(
                    'The schemaOrCallable argument has to be a valid path to XSD file or callable.'
                );
            }

            if (!$valid) {
                $messages = static::getXmlErrors($internalErrors);

                if (0 === count($messages)) {
                    $messages = [$defaultMessage];
                }

                throw new \InvalidArgumentException(implode("\n", $messages), 0, $e);
            }

            libxml_use_internal_errors($internalErrors);
        }

        return $dom;
    }

    private static function getXmlErrors(bool $internalErrors): array
    {
        $errors = [];

        foreach (libxml_get_errors() as $error) {
            $errors[] = sprintf(
                '[%s %s] %s (in %s - line %d, column %d)',
                LIBXML_ERR_WARNING === $error->level ? 'WARNING' : 'ERROR',
                $error->code,
                trim($error->message),
                $error->file ?: 'n/a',
                $error->line,
                $error->column
            );
        }

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $errors;
    }

    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }
}
