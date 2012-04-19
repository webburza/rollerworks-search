<?php

/**
 * This file is part of the RollerworksRecordFilterBundle.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rollerworks\RecordFilterBundle;

use \Rollerworks\RecordFilterBundle\FilterConfig;

/**
 * FieldsSet.
 *
 * Holds the set of filtering fields and there configuration.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class FieldsSet
{
    /**
     * @var array
     */
    protected $fields = array();

    /**
     * Set an filtering field
     *
     * @param string       $name
     * @param FilterConfig $config
     */
    public function set($name, FilterConfig $config)
    {
        $this->fields[$name] = $config;
    }

    /**
     * Replace the given filtering field.
     *
     * Same as {@see set()}, but throws an exception when there no field with the name
     *
     * @param string       $name
     * @param FilterConfig $config
     *
     * @throws \RuntimeException when there is no field with the given name
     */
    public function replace($name, FilterConfig $config)
    {
        if (!isset($this->fields[$name])) {
            throw new \RuntimeException(sprintf('Unable to replace none existend field: %s', $name));
        }

        $this->fields[$name] = $config;
    }

    /**
     * Remove the given field from the set
     *
     * @param string $name
     */
    public function remove($name)
    {
        if (isset($this->fields[$name])) {
            unset($this->fields[$name]);
        }
    }

    /**
     * Returns the configuration of the requested field
     *
     * @param string $name
     * @return FilterConfig
     *
     * @throws \RuntimeException when there is no field with the given name
     */
    public function get($name)
    {
        if (!isset($this->fields[$name])) {
            throw new \RuntimeException(sprintf('Unable to find filter field: %s', $name));
        }

        return $this->fields[$name];
    }

    /**
     * Returns all the registered fields.
     *
     * Returns as: [field-name] => {\Rollerworks\RecordFilterBundle\FilterConfig object})
     *
     * @return array
     */
    public function all()
    {
        return $this->fields;
    }

    /**
     * Returns wether there is a field with the given name.
     *
     * @param string $name
     * @return boolean
     */
    public function has($name)
    {
        return isset($this->fields[$name]);
    }
}