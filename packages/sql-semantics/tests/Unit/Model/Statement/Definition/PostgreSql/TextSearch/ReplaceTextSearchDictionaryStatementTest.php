<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\ReplaceTextSearchDictionaryStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReplaceTextSearchDictionaryStatement::class)]
#[Medium]
final class ReplaceTextSearchDictionaryStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER TEXT SEARCH CONFIGURATION s.c ALTER MAPPING FOR word REPLACE s.a WITH b');
        self::assertInstanceOf(ReplaceTextSearchDictionaryStatement::class, $statement);
        self::assertSame(['word'], $statement->tokenTypes);
        self::assertSame(['s', 'a'], $statement->dictionary->parts);
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION "s"."c" ALTER MAPPING FOR "word" REPLACE "s"."a" WITH "b"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING REPLACE a WITH b');
        self::assertInstanceOf(ReplaceTextSearchDictionaryStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql));
    }

    public function testRejectsAnInvalidOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING REPLACE a WITH b');
        self::assertInstanceOf(ReplaceTextSearchDictionaryStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withReplacement(new QualifiedName(['a', 'b', 'c']));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING REPLACE a WITH b');
        self::assertInstanceOf(ReplaceTextSearchDictionaryStatement::class, $statement);
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION "c" ALTER MAPPING REPLACE "a" WITH "b"', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithConfigurationReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING REPLACE a WITH b');
        self::assertInstanceOf(ReplaceTextSearchDictionaryStatement::class, $statement);
        $changed = $statement->withConfiguration(new QualifiedName(['d']));
        self::assertSame(['d'], $changed->configuration->parts);
        self::assertSame(['c'], $statement->configuration->parts);
    }

    public function testWithDictionaryReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING REPLACE a WITH b');
        self::assertInstanceOf(ReplaceTextSearchDictionaryStatement::class, $statement);
        $changed = $statement->withDictionary(new QualifiedName(['x']));
        self::assertSame(['x'], $changed->dictionary->parts);
    }

    public function testWithReplacementReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING REPLACE a WITH b');
        self::assertInstanceOf(ReplaceTextSearchDictionaryStatement::class, $statement);
        $changed = $statement->withReplacement(new QualifiedName(['y']));
        self::assertSame(['y'], $changed->replacement->parts);
    }

    public function testWithTokenTypesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING REPLACE a WITH b');
        self::assertInstanceOf(ReplaceTextSearchDictionaryStatement::class, $statement);
        $changed = $statement->withTokenTypes(['word']);
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION "c" ALTER MAPPING FOR "word" REPLACE "a" WITH "b"', $changed->toString());
    }
}
