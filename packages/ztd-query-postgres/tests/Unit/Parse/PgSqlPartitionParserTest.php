<?php

declare(strict_types=1);

namespace Tests\Unit\Parse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\Parse\PgSqlPartitionParser;
use ZtdQuery\Schema\TablePartitionKey;
use ZtdQuery\Schema\TablePartitionStrategy;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(PgSqlPartitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::class)]
final class PgSqlPartitionParserTest extends TestCase
{
    public function testParsesRangePartitionKeyAndBounds(): void
    {
        $parser = new PgSqlPartitionParser();
        $key = $parser->parseKey('CREATE TABLE logs (log_date DATE) PARTITION BY RANGE (log_date)');

        self::assertNotNull($key);
        self::assertSame(TablePartitionStrategy::Range, $key->strategy);
        self::assertSame(['log_date'], $key->expressions);

        $relation = $parser->parseRelation(
            "CREATE TABLE logs_2024 PARTITION OF public.logs FOR VALUES FROM ('2024-01-01') TO ('2025-01-01')",
            $key,
        );

        self::assertNotNull($relation);
        self::assertSame('logs', $relation->parentTable);
        self::assertSame(
            "(log_date) >= '2024-01-01' AND (log_date) < '2025-01-01'",
            $relation->predicate,
        );
    }

    public function testParsesListPartitionIncludingNull(): void
    {
        $parser = new PgSqlPartitionParser();
        $key = new TablePartitionKey(TablePartitionStrategy::List, ['region']);

        $relation = $parser->parseRelation(
            "CREATE TABLE accounts_local PARTITION OF accounts FOR VALUES IN ('east', 'west', NULL)",
            $key,
        );

        self::assertNotNull($relation);
        self::assertSame("((region) IN ('east', 'west') OR (region) IS NULL)", $relation->predicate);
    }

    public function testParsesDefaultPartition(): void
    {
        $parser = new PgSqlPartitionParser();
        $key = new TablePartitionKey(TablePartitionStrategy::List, ['region']);

        $relation = $parser->parseRelation('CREATE TABLE accounts_other PARTITION OF accounts DEFAULT', $key);

        self::assertNotNull($relation);
        self::assertNull($relation->predicate);
    }

    public function testParsesFiniteMultiColumnRangeAsRowComparison(): void
    {
        $parser = new PgSqlPartitionParser();
        $key = new TablePartitionKey(TablePartitionStrategy::Range, ['year', 'month']);

        $relation = $parser->parseRelation(
            'CREATE TABLE metrics_2024 PARTITION OF metrics FOR VALUES FROM (2024, 1) TO (2025, 1)',
            $key,
        );

        self::assertNotNull($relation);
        self::assertSame('ROW(year, month) >= ROW(2024, 1) AND ROW(year, month) < ROW(2025, 1)', $relation->predicate);
    }

    public function testParsesUnboundedRange(): void
    {
        $parser = new PgSqlPartitionParser();
        $key = new TablePartitionKey(TablePartitionStrategy::Range, ['id']);

        $relation = $parser->parseRelation(
            'CREATE TABLE smallest PARTITION OF values_table FOR VALUES FROM (MINVALUE) TO (10)',
            $key,
        );

        self::assertNotNull($relation);
        self::assertSame('(id) < 10', $relation->predicate);
    }

    public function testRejectsHashAndMixedUnboundedRangeInsteadOfGuessing(): void
    {
        $parser = new PgSqlPartitionParser();

        self::assertNull($parser->parseRelation(
            'CREATE TABLE values_hash PARTITION OF values_table FOR VALUES WITH (MODULUS 4, REMAINDER 0)',
            new TablePartitionKey(TablePartitionStrategy::Hash, ['id']),
        ));
        self::assertNull($parser->parseRelation(
            'CREATE TABLE values_range PARTITION OF values_table FOR VALUES FROM (1, MINVALUE) TO (2, MAXVALUE)',
            new TablePartitionKey(TablePartitionStrategy::Range, ['id', 'sequence']),
        ));
    }

    public function testRejectsMalformedPartitionClauses(): void
    {
        $parser = new PgSqlPartitionParser();

        self::assertNull($parser->parseKey('CREATE TABLE logs (id INTEGER)'));
        self::assertNull($parser->parseKey('CREATE TABLE RANGE (id)'));
        self::assertNull($parser->parseKey('CREATE TABLE logs (id INTEGER) PARTITION BY UNKNOWN (id)'));
        self::assertNull($parser->parseKey('CREATE TABLE logs (id INTEGER) PARTITION BY RANGE ()'));
        self::assertNull($parser->parseKey('CREATE TABLE logs (id INTEGER) PARTITION BY RANGE [id])'));
        self::assertNull($parser->parentTable('CREATE TABLE logs (id INTEGER)'));
        self::assertNull($parser->parentTable('CREATE TABLE child PARTITION OF [parent] DEFAULT'));
        self::assertNull($parser->parseRelation(
            'CREATE TABLE logs_2024 PARTITION OF logs FOR VALUES FROM () TO (10)',
            new TablePartitionKey(TablePartitionStrategy::Range, ['id']),
        ));
    }

    public function testParsesAllKeyStrategies(): void
    {
        $parser = new PgSqlPartitionParser();

        self::assertSame(
            TablePartitionStrategy::List,
            $parser->parseKey('CREATE TABLE accounts (region TEXT) PARTITION BY LIST (region)')?->strategy,
        );
        self::assertSame(
            TablePartitionStrategy::Hash,
            $parser->parseKey('CREATE TABLE values_table (id INTEGER) PARTITION BY HASH (id)')?->strategy,
        );
    }

    public function testRejectsMalformedBoundsIndependently(): void
    {
        $parser = new PgSqlPartitionParser();
        $range = new TablePartitionKey(TablePartitionStrategy::Range, ['id']);
        $list = new TablePartitionKey(TablePartitionStrategy::List, ['id']);

        self::assertNull($parser->parseRelation(
            'CREATE TABLE child PARTITION OF parent FOR VALUES IN (1)',
            $range,
        ));
        self::assertNull($parser->parseRelation(
            'CREATE TABLE child PARTITION OF parent FOR VALUES IN (1) TO (2)',
            $range,
        ));
        self::assertNull($parser->parseRelation(
            'CREATE TABLE child PARTITION OF parent FOR VALUES FROM (1) IN (2)',
            $range,
        ));
        self::assertNull($parser->parseRelation(
            'CREATE TABLE child PARTITION OF parent FOR VALUES FROM (1) TO ()',
            $range,
        ));
        self::assertNull($parser->parseRelation(
            'CREATE TABLE child PARTITION OF parent FOR VALUES FROM () TO (2)',
            $range,
        ));
        self::assertNull($parser->parseRelation(
            'CREATE TABLE child PARTITION OF parent FOR VALUES FROM (1, 2) TO (3, 4)',
            $range,
        ));
        self::assertNull($parser->parseRelation(
            'CREATE TABLE child PARTITION OF parent FOR VALUES FROM (MAXVALUE) TO (MAXVALUE)',
            $range,
        ));
        self::assertNull($parser->parseRelation(
            'CREATE TABLE child PARTITION OF parent FOR VALUES FROM (MINVALUE) TO (MINVALUE)',
            $range,
        ));
        self::assertNull($parser->parseRelation(
            'CREATE TABLE child PARTITION OF parent FOR VALUES FROM (1) TO (2)',
            $list,
        ));
        self::assertNull($parser->parseRelation(
            'CREATE TABLE child PARTITION OF parent FOR VALUES IN ()',
            $list,
        ));
    }

    public function testListPredicatesDistinguishValuesNullAndWhitespace(): void
    {
        $parser = new PgSqlPartitionParser();
        $key = new TablePartitionKey(TablePartitionStrategy::List, ['region']);

        self::assertSame(
            "(region) IN ('east', 'west')",
            $parser->parseRelation(
                "CREATE TABLE east_west PARTITION OF accounts FOR VALUES IN ('east', 'west')",
                $key,
            )?->predicate,
        );
        self::assertSame(
            '(region) IS NULL',
            $parser->parseRelation(
                'CREATE TABLE unset_region PARTITION OF accounts FOR VALUES IN (NULL)',
                $key,
            )?->predicate,
        );
        self::assertSame(
            "((region) IN ('east') OR (region) IS NULL)",
            $parser->parseRelation(
                "CREATE TABLE unset_region PARTITION OF accounts FOR VALUES IN (NULL, 'east')",
                $key,
            )?->predicate,
        );
        self::assertSame(
            '(id) < 10',
            $parser->parseRelation(
                'CREATE TABLE low_values PARTITION OF values_table FOR VALUES FROM ( minvalue ) TO (10)',
                new TablePartitionKey(TablePartitionStrategy::Range, ['id']),
            )?->predicate,
        );
    }

    public function testIgnoresNestedPartitionKeywordsWhenFindingTopLevelClauses(): void
    {
        $parser = new PgSqlPartitionParser();
        $key = $parser->parseKey(
            'CREATE TABLE logs (id INTEGER CHECK (PARTITION BY)) PARTITION BY RANGE (id)',
        );

        self::assertNotNull($key);
        self::assertSame(TablePartitionStrategy::Range, $key->strategy);
        self::assertSame(['id'], $key->expressions);
        self::assertSame(
            '(id) IN (1)',
            $parser->parseRelation(
                'CREATE TABLE child (id INTEGER DEFAULT 0) PARTITION OF parent FOR VALUES IN (1)',
                new TablePartitionKey(TablePartitionStrategy::List, ['id']),
            )?->predicate,
        );
    }

    public function testSkipsIncompleteKeywordPairBeforeThePartitionClause(): void
    {
        $key = (new PgSqlPartitionParser())->parseKey(
            'CREATE TABLE logs (id INTEGER) PARTITION WRONG RANGE (wrong) PARTITION BY RANGE (id)',
        );

        self::assertNotNull($key);
        self::assertSame(TablePartitionStrategy::Range, $key->strategy);
        self::assertSame(['id'], $key->expressions);
    }

    public function testNormalizesUnquotedParentNamesAndPreservesQuotedNames(): void
    {
        $parser = new PgSqlPartitionParser();

        self::assertSame(
            'logs',
            $parser->parentTable('CREATE TABLE child PARTITION OF Public.Logs DEFAULT'),
        );
        self::assertSame(
            'Logs',
            $parser->parentTable('CREATE TABLE child PARTITION OF public."Logs" DEFAULT'),
        );
    }

    public function testQualifiedIdentifierRejectsMismatchedTokenStream(): void
    {
        $stream = SqlTokenStream::tokenize('users', \ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::create());
        $tokens = [new SqlToken(SqlTokenKind::String, 'users', 0, 0, 0)];

        self::assertNull((new PgSqlPartitionParser())->qualifiedIdentifierAt($stream, $tokens, 0));
    }
    public function testParseKeyReadsHowATableIsDivided(): void
    {
        self::assertSame(
            TablePartitionStrategy::Range,
            (new PgSqlPartitionParser())->parseKey('CREATE TABLE t (id INT) PARTITION BY RANGE (id)')?->strategy,
        );
    }

    public function testParseKeyIsNothingWhereTheTableIsNotDivided(): void
    {
        self::assertNull((new PgSqlPartitionParser())->parseKey('CREATE TABLE t (id INT)'));
    }

    public function testParentTableAnswersTheTableAPartitionIsPartOf(): void
    {
        self::assertSame(
            'events',
            (new PgSqlPartitionParser())->parentTable('CREATE TABLE p PARTITION OF events DEFAULT'),
        );
    }

    public function testParentTableIsNothingWhereTheTableIsPartOfNothing(): void
    {
        self::assertNull((new PgSqlPartitionParser())->parentTable('CREATE TABLE t (id INT)'));
    }

    public function testParseRelationReadsWhichRowsAPartitionHolds(): void
    {
        $key = new TablePartitionKey(TablePartitionStrategy::Range, ['id']);

        self::assertSame(
            'events',
            (new PgSqlPartitionParser())
                ->parseRelation('CREATE TABLE p PARTITION OF events FOR VALUES FROM (1) TO (10)', $key)?->parentTable,
        );
    }

    public function testParseRelationIsNothingWhereTheTableIsPartOfNothing(): void
    {
        $key = new TablePartitionKey(TablePartitionStrategy::Range, ['id']);

        self::assertNull((new PgSqlPartitionParser())->parseRelation('CREATE TABLE t (id INT)', $key));
    }

    public function testRangePredicateAnswersWhichRowsARangePartitionHolds(): void
    {
        $sql = 'FROM (1) TO (10)';
        $tokens = SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::create())
            ->significantTokens();
        $key = new TablePartitionKey(TablePartitionStrategy::Range, ['id']);

        self::assertSame(
            '(id) >= 1 AND (id) < 10',
            (new PgSqlPartitionParser())->rangePredicate($sql, $tokens, 0, $key),
        );
    }

    public function testRangePredicateIsNothingWhereNoRangeIsWritten(): void
    {
        $sql = 'IN (1)';
        $tokens = SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::create())
            ->significantTokens();
        $key = new TablePartitionKey(TablePartitionStrategy::Range, ['id']);

        self::assertNull((new PgSqlPartitionParser())->rangePredicate($sql, $tokens, 0, $key));
    }

    public function testRangeBoundaryWritesOneEndOfTheRange(): void
    {
        self::assertSame(
            '(id) >= 1',
            (new PgSqlPartitionParser())->rangeBoundary(['id'], ['1'], '>=', 'MINVALUE'),
        );
    }

    public function testRangeBoundaryIsNothingWhereTheEndIsUnbounded(): void
    {
        self::assertNull(
            (new PgSqlPartitionParser())->rangeBoundary(['id'], ['MINVALUE'], '>=', 'MINVALUE'),
        );
    }

    public function testRangeBoundaryRefusesValuesThatDoNotLineUpWithTheKey(): void
    {
        self::assertFalse(
            (new PgSqlPartitionParser())->rangeBoundary(['id'], ['1', '2'], '>=', 'MINVALUE'),
        );
    }

    public function testParenthesizedValuesReadsTheValuesAndWhereTheyEnd(): void
    {
        $sql = '(1, 2)';
        $tokens = SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::create())
            ->significantTokens();

        self::assertSame(
            ['1', '2'],
            (new PgSqlPartitionParser())->parenthesizedValues($sql, $tokens, 0)['values'] ?? null,
        );
    }

    public function testParenthesizedValuesIsNothingWhereTheParenthesesNeverClose(): void
    {
        $sql = '(1, 2';
        $tokens = SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::create())
            ->significantTokens();

        self::assertNull((new PgSqlPartitionParser())->parenthesizedValues($sql, $tokens, 0));
    }

    public function testClosingParenthesisIndexAnswersWhereTheParenthesisCloses(): void
    {
        $tokens = SqlTokenStream::tokenize('(1)', \ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::create())
            ->significantTokens();

        self::assertSame(2, (new PgSqlPartitionParser())->closingParenthesisIndex($tokens, 0));
    }

    public function testClosingParenthesisIndexIsNothingWhereNoParenthesisOpensThere(): void
    {
        $tokens = SqlTokenStream::tokenize('1', \ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::create())
            ->significantTokens();

        self::assertNull((new PgSqlPartitionParser())->closingParenthesisIndex($tokens, 0));
    }

    public function testKeywordPairIndexAnswersWhereThePairIsWrittenTogether(): void
    {
        $tokens = SqlTokenStream::tokenize('PARTITION BY RANGE', \ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::create())
            ->significantTokens();

        self::assertSame(0, (new PgSqlPartitionParser())->keywordPairIndex($tokens, 'PARTITION', 'BY'));
    }

    public function testKeywordPairIndexIsNothingWhereOnlyOneOfThemIsWritten(): void
    {
        $tokens = SqlTokenStream::tokenize('PARTITION RANGE', \ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::create())
            ->significantTokens();

        self::assertNull((new PgSqlPartitionParser())->keywordPairIndex($tokens, 'PARTITION', 'BY'));
    }

    public function testKeywordIndexAnswersWhereTheKeywordIsWritten(): void
    {
        $tokens = SqlTokenStream::tokenize('FOR VALUES', \ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::create())
            ->significantTokens();

        self::assertSame(1, (new PgSqlPartitionParser())->keywordIndex($tokens, 'VALUES'));
    }

    public function testKeywordIndexIsNothingWhereItIsNotWritten(): void
    {
        $tokens = SqlTokenStream::tokenize('FOR', \ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::create())
            ->significantTokens();

        self::assertNull((new PgSqlPartitionParser())->keywordIndex($tokens, 'VALUES'));
    }

    public function testQualifiedIdentifierAtReadsAQualifiedNameDownToTheTable(): void
    {
        $stream = SqlTokenStream::tokenize('public.events', \ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::create());

        self::assertSame(
            'events',
            (new PgSqlPartitionParser())->qualifiedIdentifierAt($stream, $stream->significantTokens(), 0)['name'] ?? null,
        );
    }

    public function testIsSymbolReportsATokenBeingThatSymbol(): void
    {
        $tokens = SqlTokenStream::tokenize('(', \ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::create())
            ->significantTokens();

        self::assertTrue((new PgSqlPartitionParser())->isSymbol($tokens[0], '('));
    }

}
