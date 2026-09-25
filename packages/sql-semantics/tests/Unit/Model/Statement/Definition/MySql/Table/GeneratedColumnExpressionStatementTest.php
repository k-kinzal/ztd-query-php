<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\Table\GeneratedColumnExpressionStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GeneratedColumnExpressionStatement::class)]
#[Medium]
final class GeneratedColumnExpressionStatementTest extends TestCase
{
    public function testExpressionBindsOnMySql57(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build());
        $statement = $binder->bind('PARSE_GCOL_EXPR (1 + 2)');
        self::assertInstanceOf(GeneratedColumnExpressionStatement::class, $statement);
        self::assertSame(StatementKind::ParseGeneratedColumnExpression, $statement->kind);
        self::assertSame('PARSE_GCOL_EXPR((1 + 2))', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithExpressionReplacesTheExpression(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build());
        $statement = $binder->bind('PARSE_GCOL_EXPR (1 + 2)');
        $other = $binder->bind('PARSE_GCOL_EXPR (3)');
        self::assertInstanceOf(GeneratedColumnExpressionStatement::class, $statement);
        self::assertInstanceOf(GeneratedColumnExpressionStatement::class, $other);
        self::assertSame('PARSE_GCOL_EXPR(3)', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withExpression($other->expression)));
        self::assertSame($statement->expression, $statement->withOrigin($statement->origin)->expression);
    }

    public function testRejectsAnotherRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('PARSE_GCOL_EXPR (1)');
        $other = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(GeneratedColumnExpressionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new GeneratedColumnExpressionStatement($other->origin, $statement->expression);
    }

    public function testWithOriginKeepsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('PARSE_GCOL_EXPR (1)', strict: false);
        self::assertInstanceOf(GeneratedColumnExpressionStatement::class, $statement);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }
}
