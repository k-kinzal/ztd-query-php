<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\CreateTextSearchParserStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateTextSearchParserStatement::class)]
#[Medium]
final class CreateTextSearchParserStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TEXT SEARCH PARSER s.p (headline = h, lextypes = x.l, end = e, gettoken = g, start = s, start = s2)');
        self::assertInstanceOf(CreateTextSearchParserStatement::class, $statement);
        self::assertSame(['s2'], $statement->start->parts);
        self::assertSame(['h'], $statement->headline?->parts);
        self::assertSame('CREATE TEXT SEARCH PARSER "s"."p"(START = "s2", GETTOKEN = "g", END = "e", LEXTYPES = "x"."l", HEADLINE = "h")', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH PARSER p (start = s, gettoken = g, end = e, lextypes = l)');
        self::assertInstanceOf(CreateTextSearchParserStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql));
    }

    public function testRejectsAnInvalidOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH PARSER p (start = s, gettoken = g, end = e, lextypes = l)');
        self::assertInstanceOf(CreateTextSearchParserStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName(new QualifiedName(['a', 'b', 'c']));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH PARSER p (start = s, gettoken = g, end = e, lextypes = l)');
        self::assertInstanceOf(CreateTextSearchParserStatement::class, $statement);
        self::assertSame('CREATE TEXT SEARCH PARSER "p"(START = "s", GETTOKEN = "g", END = "e", LEXTYPES = "l")', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH PARSER p (start = s, gettoken = g, end = e, lextypes = l)');
        self::assertInstanceOf(CreateTextSearchParserStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['q']));
        self::assertSame(['q'], $changed->name->parts);
        self::assertSame(['p'], $statement->name->parts);
    }

    public function testWithStartReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH PARSER p (start = s, gettoken = g, end = e, lextypes = l)');
        self::assertInstanceOf(CreateTextSearchParserStatement::class, $statement);
        $changed = $statement->withStart(new QualifiedName(['s1']));
        self::assertSame(['s1'], $changed->start->parts);
    }

    public function testWithGettokenReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH PARSER p (start = s, gettoken = g, end = e, lextypes = l)');
        self::assertInstanceOf(CreateTextSearchParserStatement::class, $statement);
        $changed = $statement->withGettoken(new QualifiedName(['g1']));
        self::assertSame(['g1'], $changed->gettoken->parts);
    }

    public function testWithEndReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH PARSER p (start = s, gettoken = g, end = e, lextypes = l)');
        self::assertInstanceOf(CreateTextSearchParserStatement::class, $statement);
        $changed = $statement->withEnd(new QualifiedName(['e1']));
        self::assertSame(['e1'], $changed->end->parts);
    }

    public function testWithLextypesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH PARSER p (start = s, gettoken = g, end = e, lextypes = l)');
        self::assertInstanceOf(CreateTextSearchParserStatement::class, $statement);
        $changed = $statement->withLextypes(new QualifiedName(['l1']));
        self::assertSame(['l1'], $changed->lextypes->parts);
    }

    public function testWithHeadlineReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH PARSER p (start = s, gettoken = g, end = e, lextypes = l)');
        self::assertInstanceOf(CreateTextSearchParserStatement::class, $statement);
        $changed = $statement->withHeadline(new QualifiedName(['h1']));
        self::assertSame('CREATE TEXT SEARCH PARSER "p"(START = "s", GETTOKEN = "g", END = "e", LEXTYPES = "l", HEADLINE = "h1")', $changed->toString());
    }
}
