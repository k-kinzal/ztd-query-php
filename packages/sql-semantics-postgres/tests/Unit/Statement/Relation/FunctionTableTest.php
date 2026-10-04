<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\FunctionTable::class)]
#[Medium]
final class FunctionTableTest extends TestCase
{
    public function testDeriveRelationHasTheDefinedColumns(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT * FROM f() AS x (a integer)');
        self::assertSame(['a:Nullable'], array_map(static fn (\SqlSemantics\Statement\Shape\Field $field): string => ($field->name->value ?? '') . ':' . $field->nullability->name, $query->fields()->items ?? []));
    }

    public function testDeriveRelationAddsOrdinality(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT * FROM ROWS FROM (f() AS (a integer)) WITH ORDINALITY');
        self::assertSame(['a:Nullable', 'ordinality:NotNull'], array_map(static fn (\SqlSemantics\Statement\Shape\Field $field): string => ($field->name->value ?? '') . ':' . $field->nullability->name, $query->fields()->items ?? []));
    }

    public function testNameIsTheFunctionName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT * FROM f() AS (a integer)');
        $select = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $select);
        $from = $select->from;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Relation\FunctionTable::class, $from);
        self::assertSame('f', $from->name()?->value);
    }

    public function testRenderWritesAsBeforeDefinitionsWithoutAlias(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT * FROM LATERAL f() AS (a integer)');
        self::assertSame('SELECT * FROM LATERAL f() AS (a INTEGER)', $query->toString());
    }
}
