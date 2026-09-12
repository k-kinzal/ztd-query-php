<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Schema\Partition\PredicateCompiler;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\OptionalInsertIntoNormalizer::class)]
#[CoversClass(PredicateCompiler::class)]
final class PredicateCompilerTest extends TestCase
{
    public function testPartitionExpressionCase1(): void
    {
        $compiler = new PredicateCompiler();
        self::assertSame(['RANGE', 'YEAR(created_at)'], $compiler->partitionExpression('RANGE (YEAR(created_at))'));
        self::assertSame(['LIST', 'id'], $compiler->partitionExpression('LIST (id)'));
        $sql = 'HASH(id)';
        self::assertNull($compiler->partitionExpression($sql));
    }

    public function testPartitionExpressionCase2(): void
    {
        $compiler = new PredicateCompiler();
        self::assertSame(['RANGE', 'YEAR(created_at)'], $compiler->partitionExpression('RANGE (YEAR(created_at))'));
        self::assertSame(['LIST', 'id'], $compiler->partitionExpression('LIST (id)'));
        $sql = '';
        self::assertNull($compiler->partitionExpression($sql));
    }

    public function testPartitionExpressionCase3(): void
    {
        $compiler = new PredicateCompiler();
        self::assertSame(['RANGE', 'YEAR(created_at)'], $compiler->partitionExpression('RANGE (YEAR(created_at))'));
        self::assertSame(['LIST', 'id'], $compiler->partitionExpression('LIST (id)'));
        $sql = 'RANGE';
        self::assertNull($compiler->partitionExpression($sql));
    }

    public function testPartitionExpressionCase4(): void
    {
        $compiler = new PredicateCompiler();
        self::assertSame(['RANGE', 'YEAR(created_at)'], $compiler->partitionExpression('RANGE (YEAR(created_at))'));
        self::assertSame(['LIST', 'id'], $compiler->partitionExpression('LIST (id)'));
        $sql = 'RANGE ()';
        self::assertNull($compiler->partitionExpression($sql));
    }

    public function testPartitionExpressionCase5(): void
    {
        $compiler = new PredicateCompiler();
        self::assertSame(['RANGE', 'YEAR(created_at)'], $compiler->partitionExpression('RANGE (YEAR(created_at))'));
        self::assertSame(['LIST', 'id'], $compiler->partitionExpression('LIST (id)'));
        $sql = 'RANGE COLUMNS (id)';
        self::assertNull($compiler->partitionExpression($sql));
    }

    public function testRangePredicates(): void
    {
        $sql = 'CREATE TABLE t (id INT) PARTITION BY RANGE (id) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (20), PARTITION p2 VALUES LESS THAN MAXVALUE)';
        $statement = (new \ZtdQuery\Platform\MySql\MySqlParser())->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $statement);
        $partitions = $statement->partitions;
        self::assertNotNull($partitions);
        self::assertSame(['p0' => '(id) IS NULL OR (id) < 10', 'p1' => '(id) >= 10 AND (id) < 20', 'p2' => '(id) >= 20'], (new PredicateCompiler())->rangePredicates('id', $partitions));
    }

    public function testListPredicates(): void
    {
        $sql = 'CREATE TABLE t (id INT) PARTITION BY LIST (id) (PARTITION p0 VALUES IN (1, 2, NULL), PARTITION p1 VALUES IN (3))';
        $statement = (new \ZtdQuery\Platform\MySql\MySqlParser())->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $statement);
        $partitions = $statement->partitions;
        self::assertNotNull($partitions);
        self::assertSame(['p0' => '((id) IN (1, 2) OR (id) IS NULL)', 'p1' => '(id) IN (3)'], (new PredicateCompiler())->listPredicates('id', $partitions));
    }

    public function testPartitionValue(): void
    {
        $partition = new \PhpMyAdmin\SqlParser\Components\PartitionDefinition();
        $partition->type = 'LESS THAN';
        $partition->expr = 'MAXVALUE';
        $compiler = new PredicateCompiler();
        self::assertSame('MAXVALUE', $compiler->partitionValue($partition, 'LESS THAN'));
        self::assertNull($compiler->partitionValue($partition, 'IN'));
    }

}
