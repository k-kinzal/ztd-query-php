<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\TextSearch\MappingChange;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\MapTextSearchTokensStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MapTextSearchTokensStatement::class)]
#[Medium]
final class MapTextSearchTokensStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER TEXT SEARCH CONFIGURATION s.c ALTER MAPPING FOR word, "Url" WITH s.syn, simple');
        self::assertInstanceOf(MapTextSearchTokensStatement::class, $statement);
        self::assertSame(MappingChange::Alter, $statement->change);
        self::assertSame(['word', 'Url'], $statement->tokenTypes);
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION "s"."c" ALTER MAPPING FOR "word", "Url" WITH "s"."syn", "simple"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ADD MAPPING FOR word WITH simple');
        self::assertInstanceOf(MapTextSearchTokensStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql));
    }

    public function testRejectsAnInvalidOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ADD MAPPING FOR word WITH simple');
        self::assertInstanceOf(MapTextSearchTokensStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withTokenTypes(['']);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ADD MAPPING FOR word WITH simple');
        self::assertInstanceOf(MapTextSearchTokensStatement::class, $statement);
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION "c" ADD MAPPING FOR "word" WITH "simple"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithConfigurationReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ADD MAPPING FOR word WITH simple');
        self::assertInstanceOf(MapTextSearchTokensStatement::class, $statement);
        $changed = $statement->withConfiguration(new QualifiedName(['d']));
        self::assertSame(['d'], $changed->configuration->parts);
        self::assertSame(['c'], $statement->configuration->parts);
    }

    public function testWithChangeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ADD MAPPING FOR word WITH simple');
        self::assertInstanceOf(MapTextSearchTokensStatement::class, $statement);
        $changed = $statement->withChange(MappingChange::Alter);
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION "c" ALTER MAPPING FOR "word" WITH "simple"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithTokenTypesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ADD MAPPING FOR word WITH simple');
        self::assertInstanceOf(MapTextSearchTokensStatement::class, $statement);
        $changed = $statement->withTokenTypes(['url', 'email']);
        self::assertSame(['url', 'email'], $changed->tokenTypes);
    }

    public function testWithDictionariesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ADD MAPPING FOR word WITH simple');
        self::assertInstanceOf(MapTextSearchTokensStatement::class, $statement);
        $changed = $statement->withDictionaries([new QualifiedName(['a']), new QualifiedName(['b'])]);
        self::assertCount(2, $changed->dictionaries);
    }
}
