<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\CreateTextSearchConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateTextSearchConfigurationStatement::class)]
#[Medium]
final class CreateTextSearchConfigurationStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TEXT SEARCH CONFIGURATION s.c (parser = x, parser = pg_catalog.default)');
        self::assertInstanceOf(CreateTextSearchConfigurationStatement::class, $statement);
        self::assertSame(['pg_catalog', 'default'], $statement->parser->parts);
        self::assertSame('CREATE TEXT SEARCH CONFIGURATION "s"."c"(PARSER = "pg_catalog"."default")', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION c (parser = default)');
        self::assertInstanceOf(CreateTextSearchConfigurationStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql));
    }

    public function testRejectsAnInvalidOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION c (parser = default)');
        self::assertInstanceOf(CreateTextSearchConfigurationStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withParser(new QualifiedName(['a', 'b', 'c']));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION c (parser = default)');
        self::assertInstanceOf(CreateTextSearchConfigurationStatement::class, $statement);
        self::assertSame('CREATE TEXT SEARCH CONFIGURATION "c"(PARSER = "default")', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION c (parser = default)');
        self::assertInstanceOf(CreateTextSearchConfigurationStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['q']));
        self::assertSame(['q'], $changed->name->parts);
        self::assertSame(['c'], $statement->name->parts);
    }

    public function testWithParserReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION c (parser = default)');
        self::assertInstanceOf(CreateTextSearchConfigurationStatement::class, $statement);
        $changed = $statement->withParser(new QualifiedName(['p']));
        self::assertSame('CREATE TEXT SEARCH CONFIGURATION "c"(PARSER = "p")', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }
}
