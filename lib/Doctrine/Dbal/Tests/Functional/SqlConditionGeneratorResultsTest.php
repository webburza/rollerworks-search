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

namespace Rollerworks\Component\Search\Tests\Doctrine\Dbal\Functional;

use Doctrine\DBAL\Schema\Schema as DbSchema;
use Rollerworks\Component\Search\Doctrine\Dbal\ConditionGenerator;
use Rollerworks\Component\Search\Extension\Core\Type\BirthdayType;
use Rollerworks\Component\Search\Extension\Core\Type\ChoiceType;
use Rollerworks\Component\Search\Extension\Core\Type\DateType;
use Rollerworks\Component\Search\Extension\Core\Type\IntegerType;
use Rollerworks\Component\Search\Extension\Core\Type\MoneyType;
use Rollerworks\Component\Search\Extension\Core\Type\TextType;
use Rollerworks\Component\Search\Input\ProcessorConfig;
use Rollerworks\Component\Search\Input\StringQueryInput;
use Rollerworks\Component\Search\Tests\Doctrine\Dbal\SchemaRecord;

/**
 * Ensures the expected results are actually found.
 *
 * Uses the StringQuery input-processor for a readable condition
 * and ensures the input values are properly transformed.
 *
 * This example uses a 'classic' invoice system
 * with three tables:
 *
 * * invoice
 * * invoice_details
 * * customer
 *
 * For simplicity this example doesn't do tax calculation.
 *
 * @group functional
 */
final class SqlConditionGeneratorResultsTest extends FunctionalDbalTestCase
{
    /**
     * @var StringQueryInput
     */
    private $inputProcessor;

    protected function setUp()
    {
        parent::setUp();

        $this->inputProcessor = new StringQueryInput();
    }

    protected function setUpDbSchema(DbSchema $schema)
    {
        $customerTable = $schema->createTable('customer');
        $customerTable->addOption('collate', 'utf8_bin');
        $customerTable->addColumn('id', 'integer');
        $customerTable->addColumn('first_name', 'string');
        $customerTable->addColumn('last_name', 'string');
        $customerTable->addColumn('birthday', 'date');
        $customerTable->addColumn('regdate', 'date');
        $customerTable->setPrimaryKey(['id']);

        $invoiceTable = $schema->createTable('invoice');
        $invoiceTable->addOption('collate', 'utf8_bin');
        $invoiceTable->addColumn('id', 'integer');
        $invoiceTable->addColumn('customer', 'integer');
        $invoiceTable->addColumn('label', 'string', ['notnull' => false]);
        $invoiceTable->addColumn('pub_date', 'date', ['notnull' => false]);
        $invoiceTable->addColumn('status', 'integer');
        $invoiceTable->addColumn('price_total', 'decimal', ['scale' => 2]);
        $invoiceTable->setPrimaryKey(['id']);
        $invoiceTable->addUniqueIndex(['label']);

        $invoiceDetailsTable = $schema->createTable('invoice_details');
        $invoiceDetailsTable->addOption('collate', 'utf8_bin');
        $invoiceDetailsTable->addColumn('id', 'integer');
        $invoiceDetailsTable->addColumn('invoice', 'integer');
        $invoiceDetailsTable->addColumn('label', 'string');
        $invoiceDetailsTable->addColumn('quantity', 'integer');
        $invoiceDetailsTable->addColumn('price', 'decimal', ['scale' => 2]);
        $invoiceDetailsTable->addColumn('total', 'decimal', ['scale' => 2]);
        $invoiceDetailsTable->setPrimaryKey(['id']);
    }

    /**
     * @return SchemaRecord[]
     */
    protected function getDbRecords()
    {
        $date = function ($input) {
            return new \DateTime($input, new \DateTimeZone('UTC'));
        };

        return [
            SchemaRecord::create(
                'customer',
                [
                    'id' => 'integer',
                    'first_name' => 'string',
                    'last_name' => 'string',
                    'birthday' => 'date',
                    'regdate' => 'date',
                ]
            )
            ->records()
                ->add([1, 'Peter', 'Pang', $date('1980-11-20'), $date('2005-11-20')])
                ->add([2, 'Leroy', 'Jenkins', $date('2000-05-15'), $date('2005-05-20')])
                ->add([3, 'Doctor', 'Who', $date('2005-12-10'), $date('2005-02-20')])
                ->add([4, 'Spider', 'Pig', $date('2012-06-10'), $date('2012-07-20')])
            ->end(),

            // Two invoices are paid, one is a concept and three are unpaid
            SchemaRecord::create(
                'invoice',
                [
                    'id' => 'integer',
                    'customer' => 'integer',
                    'label' => 'string',
                    'pub_date' => 'date',
                    'status' => 'integer',
                    'price_total' => 'decimal',
                ]
            )
            ->records()
                ->add([1, 1, '2010-001', $date('2010-05-10'), 2, '100.00']) // 'Peter', 'Pang'
                ->add([2, 2, '2010-002', $date('2010-05-10'), 2, '90.00']) // 'Leroy', 'Jenkins'
                ->add([3, 2, null, null, 0, '10.00']) // concept - 'Leroy', 'Jenkins'
                // unpaid //
                ->add([4, 2, '2015-001', $date('2015-05-10'), 1, '50.00']) // 'Leroy', 'Jenkins'
                ->add([5, 3, '2015-002', $date('2015-05-01'), 1, '215.00']) // 'Doctor', 'Who'
                ->add([6, 4, '2015-003', $date('2015-05-05'), 1, '55.00']) // 'Spider', 'Pig'
            ->end(),

            SchemaRecord::create(
                'invoice_details',
                [
                    'id' => 'integer',
                    'invoice' => 'integer',
                    'label' => 'string',
                    'quantity' => 'integer',
                    'price' => 'decimal',
                    'total' => 'decimal',
                ]
            )
            ->records()
                // invoice 1
                ->add([1, 1, 'Electric Guitar', 1, '200.00', '100.00'])
                // invoice 2
                ->add([2, 2, 'Sword', 1, '15.00', '15.00'])
                ->add([3, 2, 'Shield', 1, '20.00', '20.00'])
                ->add([4, 2, 'Armor', 1, '55.00', '55.00'])
                // invoice 3
                ->add([5, 3, 'Sword', 1, '10.00', '10.00'])
                // invoice 4
                ->add([6, 4, 'Armor repair kit', 2, '50.00', '100.00'])
                // invoice 5
                ->add([7, 5, 'TARDIS Chameleon circuit', 1, '15.00', '15.00'])
                ->add([8, 5, 'Sonic Screwdriver', 10, '20.00', '200.00'])
                // invoice 6
                ->add([9, 6, 'Web shooter', 1, '10.00', '10.00'])
                ->add([10, 6, 'Cape', 1, '10.00', '10.00'])
                ->add([11, 6, 'Cape repair manual', 1, '10.00', '10.00'])
                ->add([12, 6, 'Hoof polish', 3, '10.00', '30.00'])
            ->end(),
        ];
    }

    protected function getQuery()
    {
        return <<<'SQL'
SELECT
    *, i.id AS id
FROM
    invoice AS i
JOIN
    customer AS c ON i.customer = c.id
LEFT JOIN
    invoice_details AS ir ON ir.invoice = i.id
WHERE

SQL;
    }

    protected function configureConditionGenerator(ConditionGenerator $conditionGenerator)
    {
        // Customer (by invoice relation)
        $conditionGenerator->setField('customer-first-name', 'first_name', 'c', 'string');
        $conditionGenerator->setField('customer-last-name', 'last_name', 'c', 'string');
        $conditionGenerator->setField('customer-birthday', 'birthday', 'c', 'date');
        $conditionGenerator->setField('customer-regdate', 'regdate', 'c', 'date');

        $conditionGenerator->setField('customer-name#first_name', 'first_name', 'c', 'string');
        $conditionGenerator->setField('customer-name#last_name', 'last_name', 'c', 'string');

        // Invoice
        $conditionGenerator->setField('id', 'id', 'i', 'integer');
        $conditionGenerator->setField('customer', 'customer', 'i', 'integer');
        $conditionGenerator->setField('label', 'label', 'i', 'string');
        $conditionGenerator->setField('pub-date', 'pub_date', 'i', 'date');
        $conditionGenerator->setField('status', 'status', 'i', 'integer');
        $conditionGenerator->setField('total', 'price_total', 'i', 'decimal');

        // Invoice Details
        $conditionGenerator->setField('row-label', 'label', 'ir', 'string');
        $conditionGenerator->setField('row-quantity', 'quantity', 'ir', 'integer');
        $conditionGenerator->setField('row-price', 'price', 'ir', 'decimal');
        $conditionGenerator->setField('row-total', 'total', 'ir', 'decimal');
    }

    protected function getFieldSet(bool $build = true)
    {
        $fieldSet = $this->getFactory()->createFieldSetBuilder();

        // Customer (by invoice relation)
        $fieldSet->add('customer-first-name', TextType::class);
        $fieldSet->add('customer-last-name', TextType::class);
        $fieldSet->add('customer-name', TextType::class);
        $fieldSet->add('customer-birthday', BirthdayType::class, ['pattern' => 'yyyy-MM-dd']);
        $fieldSet->add('customer-regdate', DateType::class, ['pattern' => 'yyyy-MM-dd']);

        // Invoice
        $fieldSet->add('id', IntegerType::class);
        $fieldSet->add('customer', IntegerType::class);
        $fieldSet->add('label', TextType::class);
        $fieldSet->add('pub-date', DateType::class, ['pattern' => 'yyyy-MM-dd']);
        $fieldSet->add('status', ChoiceType::class, ['choices' => ['concept' => 0, 'published' => 1, 'paid' => 2]]);
        $fieldSet->add('total', MoneyType::class);

        // Invoice Details
        $fieldSet->add('row-label', TextType::class);
        $fieldSet->add('row-quantity', IntegerType::class);
        $fieldSet->add('row-price', MoneyType::class);
        $fieldSet->add('row-total', MoneyType::class);

        return $build ? $fieldSet->getFieldSet('invoice') : $fieldSet;
    }

    /**
     * @test
     */
    public function it_finds_with_id()
    {
        $this->makeTest('id: 1, 5;', [1, 5]);
    }

    /**
     * @test
     */
    public function it_finds_with_combined_field()
    {
        $this->makeTest('customer-name: Pang, Leroy;', [1, 2, 3, 4]);
    }

    /**
     * @test
     */
    public function it_finds_with_range_and_excluding()
    {
        $this->makeTest('id: 1~7, !2;', [1, 3, 4, 5, 6]);
    }

    /**
     * @test
     */
    public function it_finds_by_customer_birthday()
    {
        $this->makeTest('customer-birthday: "2000-05-15";', range(2, 4));
    }

    /**
     * @test
     */
    public function it_finds_by_customer_birthdays()
    {
        $this->makeTest('customer-birthday: "2000-05-15", "1980-06-10";', [2, 3, 4]);
    }

    /**
     * @test
     */
    public function it_finds_with_or_group()
    {
        $this->makeTest('* customer-birthday: "1980-11-20"; pub-date: "2015-05-01";', [1, 5]);
    }

    /**
     * @test
     */
    public function it_finds_pubDate_limited_by_price()
    {
        $this->makeTest('pub-date: "2015-05-10"; total: "50.00"', [4]);
    }

    /**
     * @test
     */
    public function it_finds_by_customer_and_status()
    {
        $this->makeTest('customer: 2; status: concept;', [3]);
    }

    /**
     * @test
     */
    public function it_finds_by_customer_and_status_and_total()
    {
        $this->makeTest('customer: 2; status: paid; total: "90.00";', [2]);
    }

    /**
     * @test
     */
    public function it_finds_by_customer_and_status_or_price()
    {
        $this->makeTest('customer: 2; *(status: paid; total: "50.00";)', [2, 4]);
    }

    /**
     * @test
     */
    public function it_finds_by_status_and_label_or_quantity_limited_by_price()
    {
        // Note there is no row with quantity 5, which is resolved as its in an OR'ed group
        $this->makeTest('status: published; *(row-quantity: 5; row-label: ~*"repair"; (row-price: "50.00"));', [4]);
    }

    /**
     * @test
     */
    public function it_finds_by_excluding_equals_pattern()
    {
        $this->makeTest('row-label: ~=Armor, ~=sword;', [2]); // Invoice 3 doesn't match as "sword" is lowercase
        $this->makeTest('row-price: "15.00"; row-label: ~!=Sword;', [5]);

        // Lowercase
        $this->makeTest('row-label: ~=Armor, ~i=sword;', [2, 3]);
    }

    private function makeTest($input, array $expectedRows)
    {
        $config = new ProcessorConfig($this->getFieldSet());

        try {
            $condition = $this->inputProcessor->process($config, $input);
            $this->assertRecordsAreFound($condition, $expectedRows);
        } catch (\Exception $e) {
            self::detectSystemException($e);

            if (function_exists('dump')) {
                dump($e);
            } else {
                echo 'Please install symfony/var-dumper as dev-requirement to get a readable structure.'.PHP_EOL;

                // Don't use var-dump or print-r as this crashes php...
                echo get_class($e).'::'.(string) $e;
            }

            $this->fail('Condition contains errors.');
        }
    }
}
