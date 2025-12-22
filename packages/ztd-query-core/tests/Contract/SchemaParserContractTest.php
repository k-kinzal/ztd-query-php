<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\SchemaParser;

/**
 * Abstract contract test for SchemaParser implementations.
 *
 * Enforces contracts defined in quality-standards.md Section 1.3 and properties P-SP-1 through P-SP-6.
 */
abstract class SchemaParserContractTest extends TestCase
{
    abstract protected function createParser(): SchemaParser;

    /**
     * A valid CREATE TABLE statement in the platform's dialect.
     * Must define at least: columns, primary key, NOT NULL columns, column types, unique constraints.
     */
    abstract protected function validCreateTableSql(): string;

    /**
     * A SQL statement that is NOT a CREATE TABLE (e.g. SELECT, INSERT).
     */
    abstract protected function nonCreateTableSql(): string;

    /**
     * Valid CREATE TABLE must return a non-null TableDefinition (P-SP-5).
     */
    public function testValidCreateTableReturnsNonNull(): void
    {
        $parser = $this->createParser();
        $result = $parser->parse($this->validCreateTableSql());

        self::assertNotNull($result);
    }

    /**
     * Non-CREATE TABLE SQL must return null (P-SP-6).
     */
    public function testNonCreateTableReturnsNull(): void
    {
        $parser = $this->createParser();
        $result = $parser->parse($this->nonCreateTableSql());

        self::assertNull($result);
    }

    /**
     * primaryKeys must be a subset of columns (P-SP-1).
     */
    public function testPrimaryKeysSubsetOfColumns(): void
    {
        $parser = $this->createParser();
        $definition = $parser->parse($this->validCreateTableSql());

        self::assertNotNull($definition);

        foreach ($definition->primaryKeys as $pk) {
            self::assertContains(
                $pk,
                $definition->columns,
                sprintf('Primary key "%s" is not in columns list', $pk)
            );
        }
    }

    /**
     * notNullColumns must be a subset of columns (P-SP-3).
     */
    public function testNotNullSubsetOfColumns(): void
    {
        $parser = $this->createParser();
        $definition = $parser->parse($this->validCreateTableSql());

        self::assertNotNull($definition);

        foreach ($definition->notNullColumns as $col) {
            self::assertContains(
                $col,
                $definition->columns,
                sprintf('NOT NULL column "%s" is not in columns list', $col)
            );
        }
    }

    /**
     * Every key in columnTypes must exist in columns (P-SP-2).
     */
    public function testColumnTypesKeysSubsetOfColumns(): void
    {
        $parser = $this->createParser();
        $definition = $parser->parse($this->validCreateTableSql());

        self::assertNotNull($definition);

        foreach (array_keys($definition->columnTypes) as $col) {
            self::assertContains(
                $col,
                $definition->columns,
                sprintf('Column type key "%s" is not in columns list', $col)
            );
        }
    }

    /**
     * Every column list in uniqueConstraints must be a subset of columns (P-SP-4).
     */
    public function testUniqueConstraintColumnsSubsetOfColumns(): void
    {
        $parser = $this->createParser();
        $definition = $parser->parse($this->validCreateTableSql());

        self::assertNotNull($definition);

        foreach ($definition->uniqueConstraints as $constraintName => $constraintColumns) {
            foreach ($constraintColumns as $col) {
                self::assertContains(
                    $col,
                    $definition->columns,
                    sprintf(
                        'Unique constraint "%s" column "%s" is not in columns list',
                        $constraintName,
                        $col
                    )
                );
            }
        }
    }

    /**
     * Parsed TableDefinition must have non-empty columns.
     */
    public function testParsedDefinitionHasNonEmptyColumns(): void
    {
        $parser = $this->createParser();
        $definition = $parser->parse($this->validCreateTableSql());

        self::assertNotNull($definition);
        self::assertNotEmpty($definition->columns);
    }

    /**
     * Parsed columns must match expected column names in order.
     */
    public function testParsedColumnsMatchExpected(): void
    {
        $parser = $this->createParser();
        $definition = $parser->parse($this->validCreateTableSql());

        self::assertNotNull($definition);
        self::assertSame(
            $this->expectedColumns(),
            $definition->columns,
            'Parsed column names must match expected columns in order'
        );
    }

    /**
     * Parsed primary keys must match expected primary keys.
     */
    public function testParsedPrimaryKeysMatchExpected(): void
    {
        $parser = $this->createParser();
        $definition = $parser->parse($this->validCreateTableSql());

        self::assertNotNull($definition);
        self::assertSame(
            $this->expectedPrimaryKeys(),
            $definition->primaryKeys,
            'Parsed primary keys must match expected primary keys'
        );
    }

    /**
     * Parsed NOT NULL columns must include expected NOT NULL columns.
     */
    public function testParsedNotNullColumnsMatchExpected(): void
    {
        $parser = $this->createParser();
        $definition = $parser->parse($this->validCreateTableSql());

        self::assertNotNull($definition);

        foreach ($this->expectedNotNullColumns() as $col) {
            self::assertContains(
                $col,
                $definition->notNullColumns,
                sprintf('Column "%s" should be NOT NULL', $col)
            );
        }
    }

    /**
     * Column count must match exactly.
     */
    public function testColumnCountMatchesExpected(): void
    {
        $parser = $this->createParser();
        $definition = $parser->parse($this->validCreateTableSql());

        self::assertNotNull($definition);
        self::assertCount(
            count($this->expectedColumns()),
            $definition->columns,
            'Column count must match expected'
        );
    }

    /**
     * Return expected column names in order for the validCreateTableSql fixture.
     *
     * @return array<int, string>
     */
    protected function expectedColumns(): array
    {
        return ['id', 'name', 'email'];
    }

    /**
     * Return expected primary key column names.
     *
     * @return array<int, string>
     */
    protected function expectedPrimaryKeys(): array
    {
        return ['id'];
    }

    /**
     * Return columns that must be NOT NULL.
     *
     * @return array<int, string>
     */
    protected function expectedNotNullColumns(): array
    {
        return ['id', 'name'];
    }

    /**
     * Malformed input must return null (does not throw).
     */
    public function testMalformedInputReturnsNull(): void
    {
        $parser = $this->createParser();
        $result = $parser->parse('NOT VALID SQL AT ALL %%%');

        self::assertNull($result);
    }

    /**
     * Every key in typedColumns must exist in columns (structural invariant for ColumnType migration).
     */
    public function testTypedColumnsKeysSubsetOfColumns(): void
    {
        $parser = $this->createParser();
        $definition = $parser->parse($this->validCreateTableSql());

        self::assertNotNull($definition);

        foreach (array_keys($definition->typedColumns) as $col) {
            self::assertContains(
                $col,
                $definition->columns,
                sprintf('Typed column key "%s" is not in columns list', $col)
            );
        }
    }
}
