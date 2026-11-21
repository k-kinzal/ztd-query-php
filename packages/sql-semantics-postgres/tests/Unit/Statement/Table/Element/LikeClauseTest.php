<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeClause::class)]
#[Medium]
final class LikeClauseTest extends TestCase
{
    public function testDeriveClauseResolvesTheSourceTable(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $context = [];
        array_push($context, ...$semantics->inspect('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->inspect('CREATE TABLE n (LIKE t)', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeClause::class, $n3);
        $n4 = $statement->facts->relation($n3)->table;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable::class, $n4);
        self::assertSame(true, $n4->table === $context[0]);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $statement = $semantics->inspect('CREATE TABLE n (LIKE s.t INCLUDING ALL EXCLUDING STATISTICS)', []);
        self::assertSame('CREATE TABLE n (LIKE s.t INCLUDING ALL EXCLUDING STATISTICS)', $statement->toString());
    }

    public function testDeriveClauseReportsASequence(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $context = [$semantics->inspect('CREATE SEQUENCE s'), $semantics->inspect('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame(['relation "s" is invalid in LIKE clause'], array_map(static fn ($problem): string => $problem->message(), $semantics->inspect('CREATE TABLE c (LIKE s)', $context)->facts->diagnostics));
        self::assertSame([], array_map(static fn ($problem): string => $problem->message(), $semantics->inspect('CREATE TABLE c (LIKE v)', $context)->facts->diagnostics));
    }
}
