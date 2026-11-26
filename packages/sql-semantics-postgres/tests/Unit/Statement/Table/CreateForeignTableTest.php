<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateForeignTable::class)]
#[Medium]
final class CreateForeignTableTest extends TestCase
{
    public function testDeriveStatementDeclaresThePartition(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('CREATE FOREIGN TABLE f PARTITION OF t FOR VALUES IN (1) SERVER s', $context);
        self::assertSame([
          0 => 'a integer NotNull',
          1 => 'b integer Nullable',
          2 => 'c text Nullable',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
    }

    public function testDeriveRelationAnswersTheNewTable(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE FOREIGN TABLE f (a int) SERVER s', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateForeignTable::class, $n1);
        self::assertSame(1, count($n1->deriveRelation(new \SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation($statement->context), new \SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment($statement->context))->shape->slots));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE FOREIGN TABLE IF NOT EXISTS f (a int OPTIONS (x \'y\') NOT NULL) INHERITS (p) SERVER s OPTIONS (t \'u\')', []);
        self::assertSame('CREATE FOREIGN TABLE IF NOT EXISTS f (a INT OPTIONS (x \'y\') NOT NULL) INHERITS (p) SERVER s OPTIONS (t \'u\')', $statement->toString());
    }
}
