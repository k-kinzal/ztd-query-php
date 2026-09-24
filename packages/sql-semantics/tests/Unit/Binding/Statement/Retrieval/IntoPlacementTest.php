<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Retrieval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\Retrieval\IntoPlacement;
use SqlSemantics\Dialect;

#[CoversClass(IntoPlacement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class IntoPlacementTest extends TestCase
{
    public function testClausesIgnoresTheEmptyPostgreSqlIntoProduction(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('SELECT 1 UNION SELECT 2 INTO n');
        self::assertSame(['INTO n'], array_map(Tree::text(...), IntoPlacement::clauses($tree)));
    }

    #[TestWith(['SELECT 1 INTO n UNION SELECT 2', true])]
    #[TestWith(['(SELECT 1 INTO n) UNION SELECT 2', true])]
    #[TestWith(['SELECT 1 UNION SELECT 2 INTO n', false])]
    public function testFirstAcceptsOnlyTheLeftmostSelect(string $sql, bool $expected): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse($sql);
        self::assertSame($expected, IntoPlacement::first($tree, IntoPlacement::clauses($tree)[0]));
    }

    public function testLeftmostSkipsTheWithClause(): void
    {
        $tree = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('WITH c AS (SELECT 1) SELECT 2 UNION SELECT 3'), ['SelectStmt'])[0];
        self::assertSame('SELECT 2', Tree::text(IntoPlacement::leftmost($tree) ?? $tree));
    }

    #[TestWith(['SELECT 1 UNION SELECT 2 INTO @a', true])]
    #[TestWith(['SELECT 1 INTO @a UNION SELECT 2', false])]
    #[TestWith(['SELECT 1 INTO @a FROM (SELECT 1 UNION SELECT 2) d', true])]
    public function testLastRejectsASetOperationAfterInto(string $sql, bool $expected): void
    {
        $tree = (new DialectParser(Dialect::MySql))->parse($sql);
        self::assertSame($expected, IntoPlacement::last($tree, IntoPlacement::clauses($tree)[0]));
    }

    public function testTokensSkipsSubqueries(): void
    {
        $tree = Tree::outer((new DialectParser(Dialect::MySql))->parse('SELECT (SELECT 1) FROM t WHERE a'), ['select_stmt'])[0];
        self::assertSame(['SELECT', 'FROM', 'WHERE', 'a'], array_map(static fn ($token): string => $token->text, IntoPlacement::tokens($tree)));
    }
}
