<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\TextSearch\DictionaryOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\TypeSystem\TextSearch;

#[CoversClass(TextSearch::class)]
#[Medium]
final class TextSearchTest extends TestCase
{
    #[TestWith(['CREATE TEXT SEARCH TEMPLATE "t"(LEXIZE = "l")'])]
    #[TestWith(['CREATE TEXT SEARCH DICTIONARY "d"(TEMPLATE = "simple", "accept")'])]
    #[TestWith(['ALTER TEXT SEARCH DICTIONARY "d"("accept" = \'true\')'])]
    #[TestWith(['CREATE TEXT SEARCH CONFIGURATION "c"(COPY = "english")'])]
    public function testWriteProducesTheStatementText(string $sql): void
    {
        self::assertSame($sql, TextSearch::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql))?->toString());
    }

    #[TestWith(['ALTER TEXT SEARCH CONFIGURATION "c" ADD MAPPING FOR "word" WITH "a"'])]
    #[TestWith(['ALTER TEXT SEARCH CONFIGURATION "c" ALTER MAPPING FOR "word" REPLACE "a" WITH "b"'])]
    #[TestWith(['ALTER TEXT SEARCH CONFIGURATION "c" DROP MAPPING IF EXISTS FOR "word"'])]
    public function testMappingWritesTheConfigurationCommands(string $sql): void
    {
        self::assertSame($sql, TextSearch::mapping((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql))?->toString());
        self::assertNull(TextSearch::mapping((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    public function testCreateOmitsAbsentSettings(): void
    {
        self::assertSame('CREATE TEXT SEARCH TEMPLATE "t"(LEXIZE = "l")', TextSearch::create('TEMPLATE', new QualifiedName(['t']), ['INIT' => null, 'LEXIZE' => new QualifiedName(['l'])])->toString());
    }

    public function testSettingWritesAName(): void
    {
        self::assertSame('PARSER = "s"."p"', TextSearch::setting('PARSER', new QualifiedName(['s', 'p']))->toString());
    }

    public function testOptionWritesTheTextArgument(): void
    {
        self::assertSame('"StopWords" = \'english\'', TextSearch::option(new DictionaryOption('StopWords', 'english'))->toString());
    }

    public function testNameQuotesEachPart(): void
    {
        self::assertSame('"a"."B"', TextSearch::name(new QualifiedName(['a', 'B']))->toString());
    }

    public function testTokensSeparatesIdentifiers(): void
    {
        self::assertSame('"word", "url"', TextSearch::tokens(['word', 'url'])->toString());
    }

    #[TestWith(['CREATE TEXT SEARCH PARSER "p"(START = "s", GETTOKEN = "g", END = "e", LEXTYPES = "l", HEADLINE = "h")'])]
    #[TestWith(['CREATE TEXT SEARCH TEMPLATE "t"(INIT = "i", LEXIZE = "l")'])]
    #[TestWith(['CREATE TEXT SEARCH CONFIGURATION "c"(PARSER = "p")'])]
    #[TestWith(['CREATE TEXT SEARCH DICTIONARY "d"(TEMPLATE = "t", "a" = \'1\', "b" = \'x\')'])]
    #[TestWith(['ALTER TEXT SEARCH CONFIGURATION "c" ADD MAPPING FOR "word" WITH "simple"'])]
    public function testWriteWritesEveryDefinitionSetting(string $sql): void
    {
        self::assertSame($sql, TextSearch::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql))?->toString());
    }
}
