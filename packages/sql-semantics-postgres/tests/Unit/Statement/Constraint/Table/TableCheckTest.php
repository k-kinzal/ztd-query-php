<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TableCheck::class)]
#[Medium]
final class TableCheckTest extends TestCase
{
    public function testKindIsCheck(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, CHECK (a > 0))', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[1];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TableCheck::class, $n3);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::Check, $n3->kind());
    }

    public function testDeriveClauseChecksTheConditionAndTheAttributes(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, CHECK (a) DEFERRABLE)', []);
        self::assertSame([
          0 => 'argument of CHECK must be type boolean',
          1 => 'CHECK constraints cannot be marked DEFERRABLE',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, CONSTRAINT c CHECK (a > 0) NOT VALID NO INHERIT)', []);
        self::assertSame('CREATE TABLE t (a INT, CONSTRAINT c CHECK (a > 0) NOT VALID NO INHERIT)', $statement->toString());
    }
}
