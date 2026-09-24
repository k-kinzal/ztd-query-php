<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\FullTextBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Text\FullTextMode;
use SqlSemantics\Model\Scalar\Text\FullTextSearch;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FullTextBinder::class)]
#[Medium]
final class FullTextBinderTest extends TestCase
{
    #[TestWith(["MATCH (title) AGAINST ('w')", FullTextMode::NaturalLanguage, 1])]
    #[TestWith(["MATCH title, body AGAINST ('w' IN NATURAL LANGUAGE MODE)", FullTextMode::NaturalLanguage, 2])]
    #[TestWith(["MATCH (title) AGAINST ('w' WITH QUERY EXPANSION)", FullTextMode::QueryExpansion, 1])]
    #[TestWith(["MATCH (title, body) AGAINST ('w' IN BOOLEAN MODE)", FullTextMode::Boolean, 2])]
    public function testBindRetainsColumnsAndSearchMode(string $sql, FullTextMode $mode, int $columns): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (title TEXT, body TEXT)')))->bind('SELECT ' . $sql . ' FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $search = $query->outputs[0]->expression;
        self::assertInstanceOf(FullTextSearch::class, $search);
        self::assertSame($mode, $search->mode);
        self::assertCount($columns, $search->columns);
    }

    public function testBindLeavesOtherExpressionsAlone(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse("SELECT POSITION('a' IN 'b')");
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql));
        self::assertNull(FullTextBinder::bind($tree->find('simple_expr')[0], $scope));
    }

    #[TestWith(['', FullTextMode::NaturalLanguage])]
    #[TestWith(['IN BOOLEAN MODE', FullTextMode::Boolean])]
    #[TestWith(['IN NATURAL LANGUAGE MODE WITH QUERY EXPANSION', FullTextMode::QueryExpansion])]
    public function testModeClassifiesTheModifierClause(string $clause, FullTextMode $mode): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse("SELECT MATCH (a) AGAINST ('w' " . $clause . ')');
        self::assertSame($mode, FullTextBinder::mode($tree->find('fulltext_options')[0] ?? null));
    }
}
