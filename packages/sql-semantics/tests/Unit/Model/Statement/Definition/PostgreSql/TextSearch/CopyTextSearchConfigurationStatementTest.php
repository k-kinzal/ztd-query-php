<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\CopyTextSearchConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CopyTextSearchConfigurationStatement::class)]
#[Medium]
final class CopyTextSearchConfigurationStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TEXT SEARCH CONFIGURATION s.c (COPY = \'English\')');
        self::assertInstanceOf(CopyTextSearchConfigurationStatement::class, $statement);
        self::assertSame(['English'], $statement->copied->parts);
        self::assertSame('CREATE TEXT SEARCH CONFIGURATION "s"."c"(COPY = "English")', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION c (copy = english)');
        self::assertInstanceOf(CopyTextSearchConfigurationStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql));
    }

    public function testRejectsAnInvalidOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION c (copy = english)');
        self::assertInstanceOf(CopyTextSearchConfigurationStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withCopied(new QualifiedName(['a', 'b', 'c']));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION c (copy = english)');
        self::assertInstanceOf(CopyTextSearchConfigurationStatement::class, $statement);
        self::assertSame('CREATE TEXT SEARCH CONFIGURATION "c"(COPY = "english")', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION c (copy = english)');
        self::assertInstanceOf(CopyTextSearchConfigurationStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['q']));
        self::assertSame(['q'], $changed->name->parts);
        self::assertSame(['c'], $statement->name->parts);
    }

    public function testWithCopiedReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION c (copy = english)');
        self::assertInstanceOf(CopyTextSearchConfigurationStatement::class, $statement);
        $changed = $statement->withCopied(new QualifiedName(['simple']));
        self::assertSame('CREATE TEXT SEARCH CONFIGURATION "c"(COPY = "simple")', $changed->toString());
    }
}
