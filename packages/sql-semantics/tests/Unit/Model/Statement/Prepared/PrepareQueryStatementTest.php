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
        self::assertSame($changed->toString(), $binder->bind($changed->toString(), strict: false)->toString());
    }

    public function testDeclaredParametersReachCtesAndNestedQueries(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('PREPARE s(int) AS WITH q AS (SELECT $1 AS x) SELECT (SELECT $1) AS a, q.x FROM q');
        self::assertInstanceOf(PrepareQueryStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement->statement);
        self::assertSame('integer', $statement->statement->outputs[0]->expression->type->name);
        self::assertSame('integer', $statement->statement->outputs[1]->expression->type->name);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
