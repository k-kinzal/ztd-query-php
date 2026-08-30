<?php

declare(strict_types=1);

namespace Tests\Unit\Parse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Platform\Postgres\Parse\PgSqlSelectRelationParser;
use ZtdQuery\Platform\Postgres\Parse\PgSqlTableSampleParser;
use ZtdQuery\Platform\Postgres\Statement\PgSqlTableSample;
use ZtdQuery\Platform\Postgres\Statement\PgSqlTableSampleMethod;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(PgSqlTableSampleParser::class)]
#[UsesClass(PgSqlSelectRelationParser::class)]
#[UsesClass(PgSqlTableSample::class)]
#[UsesClass(PgSqlLexerProfile::class)]
final class PgSqlTableSampleParserTest extends TestCase
{
    public function testParsesSchemaAliasExpressionAndRepeatableSeed(): void
    {
        $sql = 'SELECT d.id FROM public.data AS d TABLESAMPLE BERNOULLI(50 + $1) REPEATABLE( 42.5 )';
        $samples = (new PgSqlTableSampleParser())->parse($sql);

        self::assertCount(1, $samples);
        self::assertSame('data', $samples[0]->tableName);
        self::assertSame('public.data', $samples[0]->sourceSql);
        self::assertSame('AS d', $samples[0]->aliasSql);
        self::assertSame(PgSqlTableSampleMethod::Bernoulli, $samples[0]->method);
        self::assertSame('50 + $1', $samples[0]->percentageSql);
        self::assertSame('42.5', $samples[0]->seedSql);
        self::assertSame('public.data AS d TABLESAMPLE BERNOULLI(50 + $1) REPEATABLE( 42.5 )', substr(
            $sql,
            $samples[0]->startOffset,
            $samples[0]->endOffset - $samples[0]->startOffset,
        ));
    }

    public function testParsesMultipleSamplesAndNestedSelects(): void
    {
        $samples = (new PgSqlTableSampleParser())->parse(
            'SELECT * FROM data TABLESAMPLE SYSTEM (100) '
            . 'JOIN (SELECT * FROM logs TABLESAMPLE BERNOULLI (25)) sampled_logs ON TRUE',
        );

        self::assertCount(2, $samples);
        self::assertSame(['data', 'logs'], array_column($samples, 'tableName'));
        self::assertSame(PgSqlTableSampleMethod::System, $samples[0]->method);
        self::assertSame(PgSqlTableSampleMethod::Bernoulli, $samples[1]->method);
    }

    public function testFindsSampleAfterUnsampledJoinSource(): void
    {
        $samples = (new PgSqlTableSampleParser())->parse(
            'SELECT * FROM data JOIN logs TABLESAMPLE bernoulli ( 25 ) ON TRUE',
        );

        self::assertCount(1, $samples);
        self::assertSame('logs', $samples[0]->tableName);
        self::assertSame(PgSqlTableSampleMethod::Bernoulli, $samples[0]->method);
        self::assertSame('25', $samples[0]->percentageSql);
    }

    public function testRemovesInheritanceMarkerFromAliasText(): void
    {
        $samples = (new PgSqlTableSampleParser())->parse(
            'SELECT * FROM data * sampled TABLESAMPLE SYSTEM (10)',
        );

        self::assertCount(1, $samples);
        self::assertSame('sampled', $samples[0]->aliasSql);
    }

    public function testReturnsNoSamplesForOrdinaryRelations(): void
    {
        self::assertSame([], (new PgSqlTableSampleParser())->parse('SELECT * FROM data JOIN logs ON TRUE'));
        self::assertSame(
            [],
            (new PgSqlTableSampleParser())->parse(
                'SELECT * FROM (SELECT * FROM data) sampled TABLESAMPLE SYSTEM (10)',
            ),
        );
    }

    public function testRejectsCustomSamplingMethod(): void
    {
        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('TABLESAMPLE method not supported');

        (new PgSqlTableSampleParser())->parse('SELECT * FROM data TABLESAMPLE SYSTEM_ROWS (10)');
    }

    #[TestWith(['SELECT * FROM data TABLESAMPLE BERNOULLI'])]
    #[TestWith(['SELECT * FROM data TABLESAMPLE BERNOULLI 10'])]
    #[TestWith(['SELECT * FROM data TABLESAMPLE BERNOULLI ()'])]
    #[TestWith(['SELECT * FROM data TABLESAMPLE BERNOULLI (10, 20)'])]
    #[TestWith(['SELECT * FROM data TABLESAMPLE BERNOULLI (10) REPEATABLE'])]
    #[TestWith(['SELECT * FROM data TABLESAMPLE BERNOULLI (10) REPEATABLE 42'])]
    #[TestWith(['SELECT * FROM data TABLESAMPLE BERNOULLI (10) REPEATABLE ()'])]
    #[TestWith(['SELECT * FROM data TABLESAMPLE BERNOULLI (10) REPEATABLE (1, 2)'])]
    public function testRejectsMalformedPercentageAndRepeatableExpressions(string $sql): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new PgSqlTableSampleParser())->parse($sql);
    }

    #[TestWith(['SELECT * FROM data TABLESAMPLE BERNOULLI 10', 'TABLESAMPLE opening parenthesis'])]
    #[TestWith(['SELECT * FROM data TABLESAMPLE BERNOULLI (10', 'TABLESAMPLE closing parenthesis'])]
    #[TestWith([
        'SELECT * FROM data TABLESAMPLE BERNOULLI (10) REPEATABLE 42',
        'REPEATABLE opening parenthesis',
    ])]
    #[TestWith([
        'SELECT * FROM data TABLESAMPLE BERNOULLI (10) REPEATABLE (42',
        'REPEATABLE closing parenthesis',
    ])]
    public function testDistinguishesMalformedOpeningAndClosingParentheses(string $sql, string $message): void
    {
        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage($message);

        (new PgSqlTableSampleParser())->parse($sql);
    }
    public function testParseSampleReadsTheSampleWrittenAfterATable(): void
    {
        self::assertCount(1, (new PgSqlTableSampleParser())->parse('SELECT * FROM t TABLESAMPLE SYSTEM (10)'));
    }

    public function testTokenAtOffsetAnswersTheTokenWrittenThere(): void
    {
        $tokens = SqlTokenStream::tokenize('SELECT 1', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame('1', (new PgSqlTableSampleParser())->tokenAtOffset($tokens, 7)?->text);
    }

    public function testTokenAtOffsetIsNothingWhereNoTokenStartsThere(): void
    {
        $tokens = SqlTokenStream::tokenize('SELECT 1', PgSqlLexerProfile::create())->significantTokens();

        self::assertNull((new PgSqlTableSampleParser())->tokenAtOffset($tokens, 3));
    }

    public function testSampleIndexAfterAnswersWhereTheSampleIsWritten(): void
    {
        $tokens = SqlTokenStream::tokenize('FROM t TABLESAMPLE SYSTEM (10)', PgSqlLexerProfile::create())
            ->significantTokens();

        self::assertSame(2, (new PgSqlTableSampleParser())->sampleIndexAfter($tokens, $tokens[1]));
    }

    public function testSampleIndexAfterIsNothingWhereNoSampleFollows(): void
    {
        $tokens = SqlTokenStream::tokenize('FROM t WHERE a = 1', PgSqlLexerProfile::create())->significantTokens();

        self::assertNull((new PgSqlTableSampleParser())->sampleIndexAfter($tokens, $tokens[1]));
    }

    public function testIsRelationBoundaryReportsAWordThatEndsTheTableAndItsAlias(): void
    {
        $tokens = SqlTokenStream::tokenize('WHERE', PgSqlLexerProfile::create())->significantTokens();

        self::assertTrue((new PgSqlTableSampleParser())->isRelationBoundary($tokens[0]));
    }

    public function testIsRelationBoundaryIsFalseForAName(): void
    {
        $tokens = SqlTokenStream::tokenize('t', PgSqlLexerProfile::create())->significantTokens();

        self::assertFalse((new PgSqlTableSampleParser())->isRelationBoundary($tokens[0]));
    }

    public function testIsOpeningParenthesisReportsAParenthesisAtTheSameLevel(): void
    {
        $tokens = SqlTokenStream::tokenize('t (1)', PgSqlLexerProfile::create())->significantTokens();

        self::assertTrue((new PgSqlTableSampleParser())->isOpeningParenthesis($tokens[1], $tokens[0]));
    }

    public function testIsOpeningParenthesisIsFalseForAnythingElse(): void
    {
        $tokens = SqlTokenStream::tokenize('t x', PgSqlLexerProfile::create())->significantTokens();

        self::assertFalse((new PgSqlTableSampleParser())->isOpeningParenthesis($tokens[1], $tokens[0]));
    }

    public function testClosingParenthesisIndexAnswersWhereTheParenthesisCloses(): void
    {
        $tokens = SqlTokenStream::tokenize('(1)', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame(2, (new PgSqlTableSampleParser())->closingParenthesisIndex($tokens, 0));
    }

    public function testClosingParenthesisIndexIsNothingWhereItNeverCloses(): void
    {
        $tokens = SqlTokenStream::tokenize('(1', PgSqlLexerProfile::create())->significantTokens();

        self::assertNull((new PgSqlTableSampleParser())->closingParenthesisIndex($tokens, 0));
    }

    public function testTokenAfterAnswersTheTokenWrittenNext(): void
    {
        $tokens = SqlTokenStream::tokenize('SELECT 1', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame('1', (new PgSqlTableSampleParser())->tokenAfter($tokens, $tokens[0])->text);
    }

    public function testSameLevelReportsTwoTokensWrittenAtTheSameDepth(): void
    {
        $tokens = SqlTokenStream::tokenize('a b', PgSqlLexerProfile::create())->significantTokens();

        self::assertTrue((new PgSqlTableSampleParser())->sameLevel($tokens[0], $tokens[1]));
    }

    public function testSameLevelIsFalseForATokenInsideParentheses(): void
    {
        $tokens = SqlTokenStream::tokenize('a (b)', PgSqlLexerProfile::create())->significantTokens();

        self::assertFalse((new PgSqlTableSampleParser())->sameLevel($tokens[0], $tokens[2]));
    }

}
