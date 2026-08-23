<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlPartitioningParser;

#[CoversClass(MySqlPartitioningParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class MySqlPartitioningParserTest extends TestCase
{
    public function testParsesRangePartitionBoundariesIncludingNullAndMaximum(): void
    {
        $parser = new Parser('CREATE TABLE events (event_date DATE) '
            . 'PARTITION BY RANGE (YEAR(event_date)) ('
            . 'PARTITION p2023 VALUES LESS THAN (2024), '
            . 'PARTITION p2024 VALUES LESS THAN (2025), '
            . 'PARTITION pmax VALUES LESS THAN MAXVALUE)');
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);

        $partitioning = (new MySqlPartitioningParser())->parse($statement);

        self::assertNotNull($partitioning);
        self::assertSame(
            ['(YEAR(event_date)) IS NULL OR (YEAR(event_date)) < 2024'],
            $partitioning->predicatesFor(['p2023']),
        );
        self::assertSame(
            ['(YEAR(event_date)) >= 2024 AND (YEAR(event_date)) < 2025'],
            $partitioning->predicatesFor(['p2024']),
        );
        self::assertSame(
            ['(YEAR(event_date)) >= 2025'],
            $partitioning->predicatesFor(['pmax']),
        );
    }

    public function testParsesListPartitionValuesIncludingNull(): void
    {
        $parser = new Parser('CREATE TABLE regions (region_id INT) '
            . 'PARTITION BY LIST (region_id) ('
            . 'PARTITION pwest VALUES IN (NULL, 1, 2), '
            . 'PARTITION peast VALUES IN (3, 4))');
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);

        $partitioning = (new MySqlPartitioningParser())->parse($statement);

        self::assertNotNull($partitioning);
        self::assertSame(
            ['((region_id) IN (1, 2) OR (region_id) IS NULL)'],
            $partitioning->predicatesFor(['pwest']),
        );
        self::assertSame(['(region_id) IN (3, 4)'], $partitioning->predicatesFor(['peast']));
    }

    public function testParsesNullOnlyListPartition(): void
    {
        $parser = new Parser('CREATE TABLE regions (region_id INT) '
            . 'PARTITION BY LIST (region_id) (PARTITION pnull VALUES IN (NULL))');
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);

        $partitioning = (new MySqlPartitioningParser())->parse($statement);

        self::assertNotNull($partitioning);
        self::assertSame(['(region_id) IS NULL'], $partitioning->predicatesFor(['pnull']));
    }

    public function testParsesSingleMaximumRangePartition(): void
    {
        $parser = new Parser('CREATE TABLE events (id INT) '
            . 'PARTITION BY RANGE (id) (PARTITION pall VALUES LESS THAN MAXVALUE)');
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);

        $partitioning = (new MySqlPartitioningParser())->parse($statement);

        self::assertNotNull($partitioning);
        self::assertSame(['TRUE'], $partitioning->predicatesFor(['pall']));
    }

    public function testMarksHashPartitionSelectionUnsupported(): void
    {
        $parser = new Parser('CREATE TABLE events (id INT) PARTITION BY HASH(id) PARTITIONS 4');
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);

        $partitioning = (new MySqlPartitioningParser())->parse($statement);

        self::assertNotNull($partitioning);
        self::assertNull($partitioning->predicatesFor(['p0']));
    }

    public function testMarksHashPartitionExpressionWithValidDelimitersUnsupported(): void
    {
        $parser = new Parser('CREATE TABLE events (id INT) PARTITION BY RANGE (id) '
            . '(PARTITION p0 VALUES LESS THAN (10))');
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);
        $statement->partitionBy = 'HASH (id)';

        $partitioning = (new MySqlPartitioningParser())->parse($statement);

        self::assertNotNull($partitioning);
        self::assertNull($partitioning->predicatesFor(['p0']));
    }

    public function testMarksBracketsInPlaceOfPartitionParenthesesUnsupported(): void
    {
        $parser = new Parser('CREATE TABLE events (id INT) PARTITION BY RANGE (id) '
            . '(PARTITION p0 VALUES LESS THAN (10))');
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);
        $statement->partitionBy = 'RANGE [id]';

        $partitioning = (new MySqlPartitioningParser())->parse($statement);

        self::assertNotNull($partitioning);
        self::assertNull($partitioning->predicatesFor(['p0']));
    }

    public function testMarksPartitionExpressionWithoutParenthesesUnsupported(): void
    {
        $parser = new Parser('CREATE TABLE events (id INT) PARTITION BY RANGE (id) '
            . '(PARTITION p0 VALUES LESS THAN (10))');
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);
        $statement->partitionBy = 'RANGE id';

        $partitioning = (new MySqlPartitioningParser())->parse($statement);

        self::assertNotNull($partitioning);
        self::assertNull($partitioning->predicatesFor(['p0']));
    }

    public function testMarksPartitionExpressionWithoutClosingParenthesisUnsupported(): void
    {
        $parser = new Parser('CREATE TABLE events (id INT) PARTITION BY RANGE (id) '
            . '(PARTITION p0 VALUES LESS THAN (10))');
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);
        $statement->partitionBy = 'RANGE (id';

        $partitioning = (new MySqlPartitioningParser())->parse($statement);

        self::assertNotNull($partitioning);
        self::assertNull($partitioning->predicatesFor(['p0']));
    }

    public function testReturnsNullForUnpartitionedTable(): void
    {
        $parser = new Parser('CREATE TABLE events (id INT)');
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);

        self::assertNull((new MySqlPartitioningParser())->parse($statement));
    }

    public function testMarksRangeWithoutDefinitionsUnsupported(): void
    {
        $parser = new Parser('CREATE TABLE events (id INT) PARTITION BY RANGE (id) '
            . '(PARTITION p0 VALUES LESS THAN (10))');
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);
        $statement->partitions = null;

        $partitioning = (new MySqlPartitioningParser())->parse($statement);

        self::assertNotNull($partitioning);
        self::assertNull($partitioning->predicatesFor(['p0']));
    }

    public function testRejectsRangeDefinitionWithListValueType(): void
    {
        $parser = new Parser('CREATE TABLE events (id INT) PARTITION BY RANGE (id) '
            . '(PARTITION p0 VALUES LESS THAN (10))');
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);
        self::assertIsArray($statement->partitions);
        $statement->partitions[0]->type = 'IN';

        $partitioning = (new MySqlPartitioningParser())->parse($statement);

        self::assertNotNull($partitioning);
        self::assertNull($partitioning->predicatesFor(['p0']));
    }

    public function testRejectsListDefinitionWithRangeValueType(): void
    {
        $parser = new Parser('CREATE TABLE events (id INT) PARTITION BY LIST (id) '
            . '(PARTITION p0 VALUES IN (10))');
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);
        self::assertIsArray($statement->partitions);
        $statement->partitions[0]->type = 'LESS THAN';

        $partitioning = (new MySqlPartitioningParser())->parse($statement);

        self::assertNotNull($partitioning);
        self::assertNull($partitioning->predicatesFor(['p0']));
    }

    public function testRejectsRangeColumnsWithoutGuessingTupleSemantics(): void
    {
        $parser = new Parser('CREATE TABLE events (id INT, created_at DATE) '
            . 'PARTITION BY RANGE COLUMNS(id, created_at) ('
            . "PARTITION p0 VALUES LESS THAN (10, '2025-01-01'))");
        $statement = $parser->statements[0] ?? null;
        self::assertInstanceOf(CreateStatement::class, $statement);

        $partitioning = (new MySqlPartitioningParser())->parse($statement);

        self::assertNotNull($partitioning);
        self::assertNull($partitioning->predicatesFor(['p0']));
    }
}
