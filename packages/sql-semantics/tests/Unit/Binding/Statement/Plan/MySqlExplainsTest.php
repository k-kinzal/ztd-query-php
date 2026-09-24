<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Plan\MySqlExplains;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Plan\ExplainConnectionStatement;
use SqlSemantics\Model\Statement\Plan\ExplainInDatabaseStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlExplains::class)]
#[Medium]
final class MySqlExplainsTest extends TestCase
{
    #[TestWith(['mysql-5.7.44', "EXPLAIN FOR CONNECTION X'0f'"])]
    #[TestWith(['mysql-5.7.44', "EXPLAIN EXTENDED FOR CONNECTION X'0f'"])]
    #[TestWith(['mysql-8.4.7', 'EXPLAIN FOR CONNECTION 0x0F'])]
    public function testTargetBindsAHexadecimalConnectionAsItsNumber(string $version, string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ExplainConnectionStatement::class, $statement);
        self::assertSame('15', $statement->connection->spelling);
        self::assertSame('EXPLAIN FOR CONNECTION 15', $statement->toString());
    }

    public function testTargetBindsTheDatabaseScopedStatement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('EXPLAIN FOR SCHEMA d INSERT INTO t VALUES (1)', strict: false);
        self::assertInstanceOf(ExplainInDatabaseStatement::class, $statement);
        self::assertSame('EXPLAIN FOR DATABASE `d` INSERT INTO `d`.`t` VALUES (1)', $statement->toString());
    }

    #[TestWith(['EXPLAIN FOR CONNECTION 1.5', InputViolation::ExplainSetting])]
    #[TestWith(['EXPLAIN FORMAT=JSON INTO @v FOR CONNECTION 1', InputViolation::ExplainCombination])]
    #[TestWith(['EXPLAIN ANALYZE FOR CONNECTION 1', InputViolation::ExplainCombination])]
    public function testTargetRejectsAnImpossibleConnectionRequest(string $sql, InputViolation $violation): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage($violation->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind($sql);
    }

    public function testConnectionReadsDecimalDigits(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('EXPLAIN FOR CONNECTION 007');
        self::assertSame('007', MySqlExplains::connection(Tree::outer($tree, ['explainable_stmt'])[0]));
    }

    #[TestWith(['f', '15'])]
    #[TestWith(['FFFFFFFFFFFFFFFF', '18446744073709551615'])]
    #[TestWith(['00', '0'])]
    public function testDecimalConvertsHexadecimalDigits(string $hex, string $decimal): void
    {
        self::assertSame($decimal, MySqlExplains::decimal($hex));
    }
}
