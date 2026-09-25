<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Plan\PostgreSqlOptions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PostgreSqlOptions::class)]
#[Medium]
final class PostgreSqlOptionsTest extends TestCase
{
    public function testReadClassifiesEveryOptionIntoItsTypedProperty(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('EXPLAIN (ANALYZE, FORMAT JSON, BUFFERS off, SERIALIZE BINARY, COSTS tr, TIMING 0, VERBOSE 1) SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Plan\ExplainStatement::class, $statement);
        $options = $statement->options;
        self::assertInstanceOf(\SqlSemantics\Model\Plan\PostgreSqlPlan::class, $options);
        self::assertTrue($options->analyze);
        self::assertTrue($options->verbose);
        self::assertTrue($options->costs);
        self::assertFalse($options->buffers);
        self::assertFalse($options->timing);
        self::assertSame(\SqlSemantics\Model\Plan\PostgreSqlFormat::Json, $options->format);
        self::assertSame(\SqlSemantics\Model\Plan\SerializationCost::Binary, $options->serialization);
    }

    public function testReadFallsBackToTheLegacyKeywordForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('EXPLAIN ANALYZE VERBOSE SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Plan\ExplainStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Plan\PostgreSqlPlan::class, $statement->options);
        self::assertTrue($statement->options->analyze);
        self::assertTrue($statement->options->verbose);
        self::assertSame(\SqlSemantics\Model\Plan\PostgreSqlFormat::Text, $statement->options->format);
        self::assertNull($statement->options->timing);
    }

    #[TestWith(['EXPLAIN (FORMAT bogus) SELECT 1', \SqlSemantics\Model\Validation\InputViolation::ExplainSetting])]
    #[TestWith(['EXPLAIN (COSTS maybe) SELECT 1', \SqlSemantics\Model\Validation\InputViolation::ExplainSetting])]
    #[TestWith(['EXPLAIN (FOO) SELECT 1', \SqlSemantics\Model\Validation\InputViolation::ExplainOption])]
    #[TestWith(['EXPLAIN (TIMING) SELECT 1', \SqlSemantics\Model\Validation\InputViolation::ExplainCombination])]
    public function testReadRejectsUnknownOptionsBadValuesAndIncompatibleCombinations(string $sql, \SqlSemantics\Model\Validation\InputViolation $violation): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage($violation->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    #[TestWith([null, true])]
    #[TestWith(['1', true])]
    #[TestWith(['TR', true])]
    #[TestWith(['YE', true])]
    #[TestWith(['ON', true])]
    #[TestWith(['0', false])]
    #[TestWith(['FAL', false])]
    #[TestWith(['N', false])]
    #[TestWith(['OFF', false])]
    public function testBooleanAcceptsPrefixesOfTheBooleanSpellings(?string $word, bool $expected): void
    {
        self::assertSame($expected, PostgreSqlOptions::boolean($word, new Node('option', 0, [new Token(0, 'ID', 'x', 0)])));
    }

    #[TestWith([''])]
    #[TestWith(['MAYBE'])]
    #[TestWith(['O'])]
    public function testBooleanRejectsAmbiguousOrUnknownWords(string $word): void
    {
        $this->expectException(InvalidSql::class);
        PostgreSqlOptions::boolean($word, new Node('option', 0, [new Token(0, 'ID', 'x', 0)]));
    }

    public function testBooleanOptionMapsFlagsToPlanPropertiesAndRejectsOthers(): void
    {
        $node = new Node('option', 0, [new Token(0, 'ID', 'x', 0)]);
        self::assertSame('analyze', PostgreSqlOptions::booleanOption('analyse', $node));
        self::assertSame('genericPlan', PostgreSqlOptions::booleanOption('generic_plan', $node));
        self::assertSame('summary', PostgreSqlOptions::booleanOption('summary', $node));
        $this->expectException(InvalidSql::class);
        PostgreSqlOptions::booleanOption('format', $node);
    }

    #[TestWith(['EXPLAIN (VERBOSE, SETTINGS, SUMMARY, MEMORY) SELECT 1', 'EXPLAIN(ANALYZE FALSE, VERBOSE TRUE, COSTS TRUE, SETTINGS TRUE, GENERIC_PLAN FALSE, BUFFERS FALSE, WAL FALSE, SUMMARY TRUE, MEMORY TRUE, SERIALIZE NONE, FORMAT TEXT) SELECT 1'])]
    #[TestWith(['EXPLAIN (ANALYZE, TIMING false, WAL, BUFFERS) SELECT 1', 'EXPLAIN(ANALYZE TRUE, VERBOSE FALSE, COSTS TRUE, SETTINGS FALSE, GENERIC_PLAN FALSE, BUFFERS TRUE, WAL TRUE, TIMING FALSE, MEMORY FALSE, SERIALIZE NONE, FORMAT TEXT) SELECT 1'])]
    #[TestWith(['EXPLAIN (GENERIC_PLAN) SELECT $1', 'EXPLAIN(ANALYZE FALSE, VERBOSE FALSE, COSTS TRUE, SETTINGS FALSE, GENERIC_PLAN TRUE, BUFFERS FALSE, WAL FALSE, MEMORY FALSE, SERIALIZE NONE, FORMAT TEXT) SELECT $1'])]
    public function testBindReadsEveryFlagOption(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)));
    }
}
