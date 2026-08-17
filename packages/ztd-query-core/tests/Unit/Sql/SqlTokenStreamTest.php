<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenDialect;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(SqlTokenStream::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenDialect::class)]
#[UsesClass(SqlTokenKind::class)]
final class SqlTokenStreamTest extends TestCase
{
    public function testSplitsOnlyTopLevelStatementTerminators(): void
    {
        $sql = 'SELECT \';\' AS value; SELECT $$a;b$$; /* ; */ SELECT (3; 4); SELECT [5; 6];;';

        self::assertSame(
            ["SELECT ';' AS value", 'SELECT $$a;b$$', '/* ; */ SELECT (3; 4)', 'SELECT [5; 6]'],
            SqlTokenStream::tokenize($sql)->splitStatements(),
        );
    }

    public function testReturnsNoStatementsForEmptyInputAndTerminators(): void
    {
        self::assertSame([], SqlTokenStream::tokenize(' ; ; ')->splitStatements());
    }

    public function testClauseIgnoresNestedKeywords(): void
    {
        $sql = "UPDATE users SET label = TRIM(BOTH 'x' FROM label), amount = CAST(raw AS DECIMAL(10,2)) WHERE id IN (SELECT id FROM source WHERE active) ORDER BY id";

        $stream = SqlTokenStream::tokenize($sql);

        self::assertSame(
            "label = TRIM(BOTH 'x' FROM label), amount = CAST(raw AS DECIMAL(10,2))",
            $stream->topLevelClause(['SET'], [['FROM'], ['WHERE'], ['ORDER', 'BY'], ['LIMIT']]),
        );
        self::assertSame(
            'id IN (SELECT id FROM source WHERE active)',
            $stream->topLevelClause(['WHERE'], [['ORDER', 'BY'], ['LIMIT']]),
        );
    }

    public function testClauseSupportsMultiWordBoundariesAndUsesEarliestEnd(): void
    {
        $sql = 'SELECT id FROM users ORDER BY created_at LIMIT 10';
        $stream = SqlTokenStream::tokenize($sql);

        self::assertSame('created_at', $stream->topLevelClause(['ORDER', 'BY'], [['LIMIT'], ['RETURNING']]));
        self::assertSame('id', $stream->topLevelClause(['SELECT'], [['LIMIT'], ['FROM']]));
        self::assertNull($stream->topLevelClause(['GROUP', 'BY'], [['LIMIT']]));
    }

    public function testClauseRejectsSequencesSplitAcrossNestingLevels(): void
    {
        $sql = 'SELECT ORDER (BY hidden) BY visible FROM source';

        self::assertNull(SqlTokenStream::tokenize($sql)->topLevelClause(['ORDER', 'BY'], [['FROM']]));
    }

    public function testClauseCanStartAtLastAvailableToken(): void
    {
        self::assertSame('', SqlTokenStream::tokenize('SET')->topLevelClause(['SET']));
    }

    public function testClauseAfterAnchorSkipsEarlierMatchingKeyword(): void
    {
        $sql = 'INSERT INTO items SELECT * FROM source WHERE active ON CONFLICT (id) DO UPDATE SET score = excluded.score WHERE items.score >= 80 RETURNING id';

        self::assertSame(
            'items.score >= 80',
            SqlTokenStream::tokenize($sql)->topLevelClauseAfter(
                ['DO', 'UPDATE', 'SET'],
                ['WHERE'],
                [['RETURNING']],
            ),
        );
    }

    public function testClauseAfterAnchorCanBeginAtFirstToken(): void
    {
        self::assertSame(
            'earlier',
            SqlTokenStream::tokenize('DO UPDATE SET WHERE earlier WHERE later')->topLevelClauseAfter(
                ['DO', 'UPDATE', 'SET'],
                ['WHERE'],
                [['WHERE']],
            ),
        );
    }

    public function testSplitsCommasOutsideParenthesesArraysAndStrings(): void
    {
        $stream = SqlTokenStream::tokenize("ARRAY[1,2], COALESCE(a, b), 'x,y', plain");

        self::assertSame(
            ['ARRAY[1,2]', 'COALESCE(a, b)', "'x,y'", 'plain'],
            $stream->splitTopLevel(),
        );
    }

    public function testSplitsWithCustomTopLevelDelimiterAndPreservesEmptyParts(): void
    {
        $stream = SqlTokenStream::tokenize(' first ; (nested ; value) ; ; last ');

        self::assertSame(
            ['first', '(nested ; value)', '', 'last'],
            $stream->splitTopLevel(';'),
        );
    }

    public function testSplitTopLevelHandlesEmptyAndTrailingDelimiterInput(): void
    {
        self::assertSame([], SqlTokenStream::tokenize('')->splitTopLevel());
        self::assertSame(['value', ''], SqlTokenStream::tokenize('value,')->splitTopLevel());
        self::assertSame(['value'], SqlTokenStream::tokenize('value')->splitTopLevel());
    }

    public function testTokenizesWhitespaceAndNestedCommentsLosslessly(): void
    {
        $sql = " \t-- note\n/* outer /* inner */ end */x";
        $tokens = SqlTokenStream::tokenize($sql)->tokens();

        self::assertSame(
            [
                [SqlTokenKind::Whitespace, " \t", 0],
                [SqlTokenKind::Comment, '-- note', 2],
                [SqlTokenKind::Whitespace, "\n", 9],
                [SqlTokenKind::Comment, '/* outer /* inner */ end */', 10],
                [SqlTokenKind::Word, 'x', 37],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text, $token->offset],
                $tokens,
            ),
        );
        self::assertSame($sql, implode('', array_map(static fn (SqlToken $token): string => $token->text, $tokens)));
        self::assertSame(
            ['x'],
            array_map(static fn (SqlToken $token): string => $token->text, SqlTokenStream::tokenize($sql)->significantTokens()),
        );
    }

    public function testTokenizesUnterminatedCommentsLosslessly(): void
    {
        self::assertSame(
            [[SqlTokenKind::Comment, '/* outer /* inner */']],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('/* outer /* inner */')->tokens(),
            ),
        );
    }

    public function testCommentDelimitersDoNotOverlapOrConsumeFollowingTokens(): void
    {
        self::assertSame(
            [
                [SqlTokenKind::Comment, '/**/'],
                [SqlTokenKind::Word, 'next'],
                [SqlTokenKind::Comment, '/*/unterminated'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('/**/next/*/unterminated')->tokens(),
            ),
        );
        self::assertSame(
            [
                [SqlTokenKind::Word, 'prefix'],
                [SqlTokenKind::Whitespace, ' '],
                [SqlTokenKind::Comment, '--'],
                [SqlTokenKind::Whitespace, "\n"],
                [SqlTokenKind::Word, 'next'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize("prefix --\nnext")->tokens(),
            ),
        );
    }

    public function testMySqlHashCommentsRemainDialectSpecific(): void
    {
        $sql = "# SELECT hidden\nDELETE FROM users WHERE payload #>> '$.name' = 'Alice'";

        self::assertSame(
            ['DELETE', 'FROM', 'users', 'WHERE', 'payload'],
            array_map(
                static fn (SqlToken $token): string => $token->text,
                SqlTokenStream::tokenize($sql, SqlTokenDialect::MySql)->significantTokens(),
            ),
        );
        self::assertSame(
            ['#', 'SELECT', 'hidden', 'DELETE', 'FROM', 'users', 'WHERE', 'payload', '#', '>', '>', "'$.name'", '=', "'Alice'"],
            array_map(
                static fn (SqlToken $token): string => $token->text,
                SqlTokenStream::tokenize($sql)->significantTokens(),
            ),
        );
    }

    public function testNestedCommentDelimitersAdvanceAsPairs(): void
    {
        self::assertSame(
            [
                [SqlTokenKind::Comment, '/*/**/*/'],
                [SqlTokenKind::Word, 'tail'],
                [SqlTokenKind::Comment, '/*/*/x*/tail'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('/*/**/*/tail/*/*/x*/tail')->tokens(),
            ),
        );
    }

    public function testAsteriskWithoutOpeningSlashIsNotAComment(): void
    {
        self::assertSame(
            [
                [SqlTokenKind::Word, 'value'],
                [SqlTokenKind::Symbol, '*'],
                [SqlTokenKind::Symbol, '*'],
                [SqlTokenKind::Word, 'not_comment'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('value**not_comment')->tokens(),
            ),
        );
    }

    public function testTokenizesQuotedValuesAndIdentifiers(): void
    {
        $sql = "'it''s' 'a\\\\\'b' \"a\"\"b\" `a``b` 'open";

        self::assertSame(
            [
                [SqlTokenKind::String, "'it''s'"],
                [SqlTokenKind::Whitespace, ' '],
                [SqlTokenKind::String, "'a\\\\\'b'"],
                [SqlTokenKind::Whitespace, ' '],
                [SqlTokenKind::QuotedIdentifier, '"a""b"'],
                [SqlTokenKind::Whitespace, ' '],
                [SqlTokenKind::QuotedIdentifier, '`a``b`'],
                [SqlTokenKind::Whitespace, ' '],
                [SqlTokenKind::String, "'open"],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize($sql)->tokens(),
            ),
        );
    }

    public function testQuotedEscapeAtEndRemainsInsideUnterminatedValue(): void
    {
        self::assertSame(
            [[SqlTokenKind::String, "'value\x5c"]],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize("'value\x5c")->tokens(),
            ),
        );
    }

    public function testQuotedEscapeControlsWhereTheValueEnds(): void
    {
        self::assertSame(
            [
                [SqlTokenKind::String, "'''\x5c'x'"],
                [SqlTokenKind::Whitespace, ' '],
                [SqlTokenKind::String, "'\x5cx'"],
                [SqlTokenKind::Whitespace, ' '],
                [SqlTokenKind::Word, 'tail'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize("'''\x5c'x' '\x5cx' tail")->tokens(),
            ),
        );
    }

    public function testTokenizesDollarStringsAndParameters(): void
    {
        $sql = '$$body$$ $tag$body$tag$ $1 $123 ? :name :: : $word';

        self::assertSame(
            [
                [SqlTokenKind::String, '$$body$$'],
                [SqlTokenKind::String, '$tag$body$tag$'],
                [SqlTokenKind::Parameter, '$1'],
                [SqlTokenKind::Parameter, '$123'],
                [SqlTokenKind::Parameter, '?'],
                [SqlTokenKind::Parameter, ':name'],
                [SqlTokenKind::Symbol, ':'],
                [SqlTokenKind::Symbol, ':'],
                [SqlTokenKind::Symbol, ':'],
                [SqlTokenKind::Symbol, '$'],
                [SqlTokenKind::Word, 'word'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize($sql)->significantTokens(),
            ),
        );
    }

    public function testParametersStopBeforeFollowingWordAndAtEndOfInput(): void
    {
        self::assertSame(
            [
                [SqlTokenKind::Parameter, '$1'],
                [SqlTokenKind::Word, 'x'],
                [SqlTokenKind::Parameter, ':end'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('$1x :end')->significantTokens(),
            ),
        );
    }

    public function testTokenizesUnterminatedDollarString(): void
    {
        self::assertSame(
            [[SqlTokenKind::String, '$tag$body']],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('$tag$body')->tokens(),
            ),
        );
    }

    public function testDollarTagMustStartAtCurrentOffset(): void
    {
        self::assertSame(
            [
                [SqlTokenKind::Symbol, '$'],
                [SqlTokenKind::Word, 'bad'],
                [SqlTokenKind::Symbol, '-'],
                [SqlTokenKind::String, '$tag$body$tag$'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('$bad-$tag$body$tag$')->tokens(),
            ),
        );
    }

    public function testTokenizesWordsAndEveryNumberForm(): void
    {
        $sql = "_word2\$tail café9 \x80 0 9_000 0xCA_FE 0Xb 0y 1.25 2e3 3E+4 4e-5 5. 6eX";

        self::assertSame(
            [
                [SqlTokenKind::Word, '_word2$tail'],
                [SqlTokenKind::Word, 'café9'],
                [SqlTokenKind::Word, "\x80"],
                [SqlTokenKind::Number, '0'],
                [SqlTokenKind::Number, '9_000'],
                [SqlTokenKind::Number, '0xCA_FE'],
                [SqlTokenKind::Number, '0Xb'],
                [SqlTokenKind::Number, '0'],
                [SqlTokenKind::Word, 'y'],
                [SqlTokenKind::Number, '1.25'],
                [SqlTokenKind::Number, '2e3'],
                [SqlTokenKind::Number, '3E+4'],
                [SqlTokenKind::Number, '4e-5'],
                [SqlTokenKind::Number, '5.'],
                [SqlTokenKind::Number, '6e'],
                [SqlTokenKind::Word, 'X'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize($sql)->significantTokens(),
            ),
        );
    }

    public function testTracksParenthesisAndBracketDepthWithoutUnderflow(): void
    {
        self::assertSame(
            [
                ['(', 0, 0],
                ['[', 1, 0],
                ['x', 1, 1],
                [']', 1, 0],
                [',', 1, 0],
                ['(', 1, 0],
                ['y', 2, 0],
                [')', 1, 0],
                [')', 0, 0],
                ['[', 0, 0],
                ['[', 0, 1],
                ['z', 0, 2],
                [']', 0, 1],
                [']', 0, 0],
                [')', 0, 0],
                [']', 0, 0],
                ['(', 0, 0],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->text, $token->depth, $token->bracketDepth],
                SqlTokenStream::tokenize('([x],(y))[[z]])](')->significantTokens(),
            ),
        );
    }

    public function testFindsFirstTopLevelKeywordAfterLeadingComment(): void
    {
        self::assertSame(
            'WITH',
            SqlTokenStream::tokenize('/* SELECT */ WITH data AS (SELECT 1) SELECT * FROM data')->firstTopLevelKeyword(),
        );
    }

    public function testFirstTopLevelKeywordIgnoresNestedWordsAndCanBeAbsent(): void
    {
        self::assertSame('UPDATE', SqlTokenStream::tokenize('(SELECT hidden) [DELETE hidden] update users')->firstTopLevelKeyword());
        self::assertNull(SqlTokenStream::tokenize("123 + 'SELECT'")->firstTopLevelKeyword());
    }

    public function testKeepsArithmeticOperatorsOutsideNumberTokens(): void
    {
        $tokens = SqlTokenStream::tokenize('SELECT 1+2, 3.5e-2')->significantTokens();

        self::assertSame(
            ['SELECT', '1', '+', '2', ',', '3.5e-2'],
            array_map(static fn (SqlToken $token): string => $token->text, $tokens),
        );
    }

    public function testFindsOnlyFromClausesOwnedBySelectScopes(): void
    {
        $sql = 'SELECT EXTRACT(YEAR FROM event_date) FROM events WHERE id IN (SELECT id FROM archived) UNION SELECT id FROM current_events';

        self::assertSame(
            ['events', 'archived', 'current_events'],
            SqlTokenStream::tokenize($sql)->selectFromClauses(),
        );
    }

    public function testSelectFromClausesStopAtEveryClauseBoundary(): void
    {
        $sql = 'SELECT * FROM a WHERE x; SELECT * FROM b GROUP BY x; SELECT * FROM c HAVING x; SELECT * FROM d ORDER BY x; SELECT * FROM e LIMIT 1; SELECT * FROM f OFFSET 1; SELECT * FROM g FOR UPDATE; SELECT * FROM h RETURNING x';

        self::assertSame(
            ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'],
            SqlTokenStream::tokenize($sql)->selectFromClauses(),
        );
    }

    public function testSelectFromClausesTrackSetOperationScopes(): void
    {
        $sql = 'SELECT * FROM first UNION SELECT * FROM second INTERSECT SELECT * FROM third EXCEPT SELECT * FROM fourth';

        self::assertSame(
            ['first', 'second', 'third', 'fourth'],
            SqlTokenStream::tokenize($sql)->selectFromClauses(),
        );
    }

    public function testEverySetOperationReleasesItsSelectScope(): void
    {
        $sql = 'SELECT 1 UNION FROM invalid_union; SELECT 1 INTERSECT FROM invalid_intersect; SELECT 1 EXCEPT FROM invalid_except';

        self::assertSame([], SqlTokenStream::tokenize($sql)->selectFromClauses());
    }

    public function testSelectFromClausesTrackParenthesisAndBracketScopesIndependently(): void
    {
        $sql = 'SELECT [SELECT * FROM bracketed] FROM (SELECT * FROM nested) outer_table';

        self::assertSame(
            ['bracketed', '(SELECT * FROM nested) outer_table', 'nested'],
            SqlTokenStream::tokenize($sql)->selectFromClauses(),
        );
    }

    public function testSelectWithoutFromDoesNotOwnLaterFromClause(): void
    {
        $sql = 'SELECT 1 UNION FROM invalid';

        self::assertSame([], SqlTokenStream::tokenize($sql)->selectFromClauses());
    }

    public function testSelectOwnsOnlyItsFirstFromClause(): void
    {
        $sql = 'SELECT * FROM users WHERE id FROM invalid';

        self::assertSame(['users'], SqlTokenStream::tokenize($sql)->selectFromClauses());
    }

    public function testSelectFromEndIgnoresNestedClauseBoundaries(): void
    {
        $sql = 'SELECT * FROM (SELECT * FROM nested WHERE active) alias WHERE visible; SELECT * FROM source GROUP (BY hidden) WHERE valid; SELECT * FROM another ORDER [BY hidden] LIMIT 1';

        self::assertSame(
            ['(SELECT * FROM nested WHERE active) alias', 'nested', 'source GROUP (BY hidden)', 'another ORDER [BY hidden]'],
            SqlTokenStream::tokenize($sql)->selectFromClauses(),
        );
    }

    public function testIncompleteMultiWordBoundaryRemainsInFromClause(): void
    {
        self::assertSame(
            ['source GROUP'],
            SqlTokenStream::tokenize('SELECT * FROM source GROUP')->selectFromClauses(),
        );
    }

    public function testFromSearchStartsAfterFromToken(): void
    {
        self::assertSame(
            ['table'],
            SqlTokenStream::tokenize('SELECT WHERE FROM table')->selectFromClauses(),
        );
        self::assertSame(
            [],
            SqlTokenStream::tokenize('SELECT * FROM WHERE value')->selectFromClauses(),
        );
    }
}
