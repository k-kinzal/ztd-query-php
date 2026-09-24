<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\DropTextSearchMappingStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropTextSearchMappingStatement::class)]
#[Medium]
final class DropTextSearchMappingStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER TEXT SEARCH CONFIGURATION s.c DROP MAPPING IF EXISTS FOR word, url');
        self::assertInstanceOf(DropTextSearchMappingStatement::class, $statement);
        self::assertTrue($statement->ifExists);
        self::assertSame(['word', 'url'], $statement->tokenTypes);
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION "s"."c" DROP MAPPING IF EXISTS FOR "word", "url"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c DROP MAPPING FOR word');
        self::assertInstanceOf(DropTextSearchMappingStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql));
    }

    public function testRejectsAnInvalidOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c DROP MAPPING FOR word');
        self::assertInstanceOf(DropTextSearchMappingStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withTokenTypes(['']);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c DROP MAPPING FOR word');
        self::assertInstanceOf(DropTextSearchMappingStatement::class, $statement);
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION "c" DROP MAPPING FOR "word"', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithConfigurationReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c DROP MAPPING FOR word');
        self::assertInstanceOf(DropTextSearchMappingStatement::class, $statement);
        $changed = $statement->withConfiguration(new QualifiedName(['d']));
        self::assertSame(['d'], $changed->configuration->parts);
        self::assertSame(['c'], $statement->configuration->parts);
    }

    public function testWithTokenTypesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c DROP MAPPING FOR word');
        self::assertInstanceOf(DropTextSearchMappingStatement::class, $statement);
        $changed = $statement->withTokenTypes(['url']);
        self::assertSame(['url'], $changed->tokenTypes);
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c DROP MAPPING FOR word');
        self::assertInstanceOf(DropTextSearchMappingStatement::class, $statement);
        $changed = $statement->withIfExists(true);
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION "c" DROP MAPPING IF EXISTS FOR "word"', $changed->toString());
    }
}
