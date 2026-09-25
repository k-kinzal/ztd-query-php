<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Prepared\PrepareQueryStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PrepareQueryStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class PrepareQueryStatementTest extends TestCase
{
    public function testWithOriginPreservesRequiredOperandsAndSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('PREPARE s(int) AS SELECT $1', strict: false);
        self::assertInstanceOf(PrepareQueryStatement::class, $statement);
        self::assertSame('integer', $statement->parameterTypes[0]->name);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement->statement);
        self::assertSame('integer', $statement->statement->outputs[0]->expression->type->name);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('new-scope', $statement->source, Dialect::PostgreSql));
        self::assertSame('new-scope', $changed->scopeId);
        self::assertNotSame($statement, $changed);
        self::assertSame('PREPARE "s"(integer) AS SELECT $1', $changed->toString());
        self::assertSame($changed->toString(), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($changed->toString(), strict: false)));
    }

    public function testDeclaredParametersReachCtesAndNestedQueries(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('PREPARE s(int) AS WITH q AS (SELECT $1 AS x) SELECT (SELECT $1) AS a, q.x FROM q');
        self::assertInstanceOf(PrepareQueryStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement->statement);
        self::assertSame('integer', $statement->statement->outputs[0]->expression->type->name);
        self::assertSame('integer', $statement->statement->outputs[1]->expression->type->name);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('PREPARE s AS SELECT 1');
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        self::assertInstanceOf(PrepareQueryStatement::class, $statement);
        $this->expectExceptionObject(new \SqlSemantics\Model\Validation\InvalidStructure('This prepared-statement form requires PostgreSql.'));
        new PrepareQueryStatement($origin, 's', $statement->statement);
    }

    public function testRejectsAQueryOfAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('PREPARE s AS SELECT 1');
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(PrepareQueryStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $this->expectExceptionObject(new \SqlSemantics\Model\Validation\InvalidStructure('The prepared query must use the enclosing dialect.'));
        new PrepareQueryStatement($statement->origin, 's', $query);
    }

    public function testRejectsAParameterTypeOfAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('PREPARE s AS SELECT 1');
        self::assertInstanceOf(PrepareQueryStatement::class, $statement);
        $this->expectExceptionObject(new \SqlSemantics\Model\Validation\InvalidStructure('Declared parameter types must use the enclosing dialect.'));
        new PrepareQueryStatement($statement->origin, 's', $statement->statement, [\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer')]);
    }
}
