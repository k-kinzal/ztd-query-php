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
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE TABLE n (LIKE t)', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeClause::class, $n3);
        $n4 = $statement->facts->relation($n3)->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $n4);
        self::assertSame(true, $n4->table === $context[0]);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (LIKE s.t INCLUDING ALL EXCLUDING STATISTICS)', []);
        self::assertSame('CREATE TABLE n (LIKE s.t INCLUDING ALL EXCLUDING STATISTICS)', $statement->toString());
    }
}
