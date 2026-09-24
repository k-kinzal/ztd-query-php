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
use SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\AlterTextSearchDictionaryStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterTextSearchDictionaryStatement::class)]
#[Medium]
final class AlterTextSearchDictionaryStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER TEXT SEARCH DICTIONARY s.d (StopWords = \'x\', accept)');
        self::assertInstanceOf(AlterTextSearchDictionaryStatement::class, $statement);
        self::assertSame('stopwords', $statement->options[0]->name);
        self::assertNull($statement->options[1]->value);
        self::assertSame('ALTER TEXT SEARCH DICTIONARY "s"."d"("stopwords" = \'x\', "accept")', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH DICTIONARY d (accept)');
        self::assertInstanceOf(AlterTextSearchDictionaryStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql));
    }

    public function testRejectsAnInvalidOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH DICTIONARY d (accept)');
        self::assertInstanceOf(AlterTextSearchDictionaryStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withDictionary(new QualifiedName(['a', 'b', 'c']));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH DICTIONARY d (accept)');
        self::assertInstanceOf(AlterTextSearchDictionaryStatement::class, $statement);
        self::assertSame('ALTER TEXT SEARCH DICTIONARY "d"("accept")', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithDictionaryReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH DICTIONARY d (accept)');
        self::assertInstanceOf(AlterTextSearchDictionaryStatement::class, $statement);
        $changed = $statement->withDictionary(new QualifiedName(['s', 'e']));
        self::assertSame(['s', 'e'], $changed->dictionary->parts);
        self::assertSame(['d'], $statement->dictionary->parts);
    }

    public function testWithOptionsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH DICTIONARY d (accept)');
        self::assertInstanceOf(AlterTextSearchDictionaryStatement::class, $statement);
        $changed = $statement->withOptions([new DictionaryOption('accept', 'true')]);
        self::assertSame('ALTER TEXT SEARCH DICTIONARY "d"("accept" = \'true\')', $changed->toString());
    }
}
