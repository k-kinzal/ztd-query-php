<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\QueryTables::class)]
#[Medium]
final class QueryTablesTest extends TestCase
{
    public function testNameIsTheSchemaOfAnEnclosingCreateSchema(): void
    {
        self::assertSame('s', (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\QueryTables())->name(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('v')), \SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence::Permanent, new \SqlSemantics\Statement\Identifier\Name('s'))->schema?->value);
    }

    public function testTableTurnsUnknownIntoText(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE TABLE n (x, y, z) AS SELECT \'a\', a FROM t', $context);
        self::assertSame([
          0 =>
           [
            0 => 'x text Nullable',
            1 => 'y integer Nullable',
          ],
          1 =>
           [
            0 => 'too many column names were specified',
          ],
        ], [array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns), array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics)]);
    }

    public function testTableDeclaresEveryNamedColumnOfADependentType(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE x AS SELECT f(1) AS a, NULL AS b, 2 AS c', []);
        $table = $operation->declarations()[0];
        self::assertTrue($table->complete);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Undetermined::class, $table->columns[0]->type);
        self::assertSame([\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text, \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4], [$table->columns[1]->type, $table->columns[2]->type]);
    }

    public function testTableIsIncompleteAtAnOpenRow(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE VIEW w AS SELECT 1 AS k, * FROM zz');
        self::assertFalse($operation->declarations()[0]->complete);
        self::assertCount(1, $operation->declarations()[0]->columns);
    }
}
