<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\CreateTextSearchTemplateStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateTextSearchTemplateStatement::class)]
#[Medium]
final class CreateTextSearchTemplateStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TEXT SEARCH TEMPLATE s.t (lexize = l, init = i)');
        self::assertInstanceOf(CreateTextSearchTemplateStatement::class, $statement);
        self::assertSame(['i'], $statement->init?->parts);
        self::assertSame('CREATE TEXT SEARCH TEMPLATE "s"."t"(INIT = "i", LEXIZE = "l")', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH TEMPLATE t (lexize = l)');
        self::assertInstanceOf(CreateTextSearchTemplateStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql));
    }

    public function testRejectsAnInvalidOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH TEMPLATE t (lexize = l)');
        self::assertInstanceOf(CreateTextSearchTemplateStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName(new QualifiedName(['a', 'b', 'c']));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH TEMPLATE t (lexize = l)');
        self::assertInstanceOf(CreateTextSearchTemplateStatement::class, $statement);
        self::assertSame('CREATE TEXT SEARCH TEMPLATE "t"(LEXIZE = "l")', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH TEMPLATE t (lexize = l)');
        self::assertInstanceOf(CreateTextSearchTemplateStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['q']));
        self::assertSame(['q'], $changed->name->parts);
        self::assertSame(['t'], $statement->name->parts);
    }

    public function testWithLexizeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH TEMPLATE t (lexize = l)');
        self::assertInstanceOf(CreateTextSearchTemplateStatement::class, $statement);
        $changed = $statement->withLexize(new QualifiedName(['l2']));
        self::assertSame(['l2'], $changed->lexize->parts);
    }

    public function testWithInitReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH TEMPLATE t (lexize = l)');
        self::assertInstanceOf(CreateTextSearchTemplateStatement::class, $statement);
        $changed = $statement->withInit(new QualifiedName(['i2']));
        self::assertSame('CREATE TEXT SEARCH TEMPLATE "t"(INIT = "i2", LEXIZE = "l")', $changed->toString());
    }
}
