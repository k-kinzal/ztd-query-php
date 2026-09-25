<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\TextSearch\DictionaryOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\CreateTextSearchDictionaryStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateTextSearchDictionaryStatement::class)]
#[Medium]
final class CreateTextSearchDictionaryStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TEXT SEARCH DICTIONARY s.d (accept = false, template = simple, template = pg_catalog.simple, StopWords = 1.50)');
        self::assertInstanceOf(CreateTextSearchDictionaryStatement::class, $statement);
        self::assertSame(['pg_catalog', 'simple'], $statement->template->parts);
        self::assertSame('1.50', $statement->options[1]->value);
        self::assertSame('CREATE TEXT SEARCH DICTIONARY "s"."d"(TEMPLATE = "pg_catalog"."simple", "accept" = \'false\', "stopwords" = \'1.50\')', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH DICTIONARY d (template = simple)');
        self::assertInstanceOf(CreateTextSearchDictionaryStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql));
    }

    public function testRejectsAnInvalidOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH DICTIONARY d (template = simple)');
        self::assertInstanceOf(CreateTextSearchDictionaryStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withTemplate(new QualifiedName(['a', 'b', 'c']));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH DICTIONARY d (template = simple)');
        self::assertInstanceOf(CreateTextSearchDictionaryStatement::class, $statement);
        self::assertSame('CREATE TEXT SEARCH DICTIONARY "d"(TEMPLATE = "simple")', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH DICTIONARY d (template = simple)');
        self::assertInstanceOf(CreateTextSearchDictionaryStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['q']));
        self::assertSame(['q'], $changed->name->parts);
        self::assertSame(['d'], $statement->name->parts);
    }

    public function testWithTemplateReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH DICTIONARY d (template = simple)');
        self::assertInstanceOf(CreateTextSearchDictionaryStatement::class, $statement);
        $changed = $statement->withTemplate(new QualifiedName(['ispell']));
        self::assertSame(['ispell'], $changed->template->parts);
    }

    public function testWithOptionsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH DICTIONARY d (template = simple)');
        self::assertInstanceOf(CreateTextSearchDictionaryStatement::class, $statement);
        $changed = $statement->withOptions([new DictionaryOption('dictfile', 'english')]);
        self::assertSame('CREATE TEXT SEARCH DICTIONARY "d"(TEMPLATE = "simple", "dictfile" = \'english\')', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }
}
