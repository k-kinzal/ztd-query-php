<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlLexerProfiles;
use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(SqlTokenStream::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlLexerProfile::class)]
#[UsesClass(SqlTokenKind::class)]
final class SqlTokenStreamTest extends TestCase
{
    public function testNavigatesAdjacentSignificantTokensByIdentity(): void
    {
        $stream = SqlTokenStream::tokenize('MATCH /* ignored */ (title)', FakeSqlLexerProfiles::standard());
        $tokens = $stream->significantTokens();

        self::assertNull($stream->significantTokenBefore($tokens[0]));
        self::assertSame('MATCH', $stream->significantTokenBefore($tokens[1])?->text);
        self::assertSame('(', $stream->significantTokenAfter($tokens[0])?->text);
        self::assertNull($stream->significantTokenAfter($tokens[3]));
    }

    public function testRejectsAdjacentLookupForATokenFromAnotherStream(): void
    {
        $foreign = SqlTokenStream::tokenize('MATCH', FakeSqlLexerProfiles::standard())->significantTokens()[0];
        $stream = SqlTokenStream::tokenize('MATCH(title)', FakeSqlLexerProfiles::standard());

        self::assertNull($stream->significantTokenBefore($foreign));
        self::assertNull($stream->significantTokenAfter($foreign));
    }

    public function testFindsMatchingParenthesisAcrossNestedGroups(): void
    {
        $stream = SqlTokenStream::tokenize('MATCH((title), body) AGAINST (?)', FakeSqlLexerProfiles::standard());
        $tokens = $stream->significantTokens();
        $columnsClosing = $stream->matchingClosingNestingToken($tokens[1]);
        $queryClosing = $stream->matchingClosingNestingToken($tokens[9]);

        self::assertNotNull($columnsClosing);
        self::assertNotNull($queryClosing);
        self::assertSame(')', $columnsClosing->text);
        self::assertSame(19, $columnsClosing->offset);
        self::assertSame(31, $queryClosing->offset);
    }

    public function testRejectsInvalidForeignAndUnclosedOpeningParentheses(): void
    {
        $stream = SqlTokenStream::tokenize('MATCH(title', FakeSqlLexerProfiles::standard());
        $tokens = $stream->significantTokens();
        $foreign = SqlTokenStream::tokenize('(?)', FakeSqlLexerProfiles::standard())->significantTokens()[0];
        $closed = SqlTokenStream::tokenize('MATCH(title)', FakeSqlLexerProfiles::standard());
        $closedTokens = $closed->significantTokens();
        $closingSymbols = SqlTokenStream::tokenize(') )', FakeSqlLexerProfiles::standard());
        $closingTokens = $closingSymbols->significantTokens();

        self::assertNull($stream->matchingClosingNestingToken($tokens[0]));
        self::assertNull($stream->matchingClosingNestingToken($tokens[1]));
        self::assertNull($stream->matchingClosingNestingToken($foreign));
        self::assertNull($closed->matchingClosingNestingToken($closedTokens[0]));
        self::assertNull($closingSymbols->matchingClosingNestingToken($closingTokens[0]));
    }

    public function testReadsWordAndQuotedIdentifiersAtSignificantTokenOffsets(): void
    {
        $profile = FakeSqlLexerProfiles::allCapabilities();
        $stream = SqlTokenStream::tokenize('plain "quo""ted" `tick``ed` [bracket name]', $profile);

        self::assertSame(['name' => 'plain', 'next' => 1], $stream->identifierAt());
        self::assertSame(['name' => 'quo"ted', 'next' => 2], $stream->identifierAt(1));
        self::assertSame(['name' => 'tick`ed', 'next' => 3], $stream->identifierAt(2));
        self::assertSame(['name' => 'bracket name', 'next' => 4], $stream->identifierAt(3));
    }

    public function testIdentifierAtRejectsMissingAndNonIdentifierTokens(): void
    {
        $standard = FakeSqlLexerProfiles::standard();
        $allCapabilities = FakeSqlLexerProfiles::allCapabilities();

        self::assertNull(SqlTokenStream::tokenize('', $standard)->identifierAt());
        self::assertNull(SqlTokenStream::tokenize('42', $standard)->identifierAt());
        self::assertNull(SqlTokenStream::tokenize("'value'", $standard)->identifierAt());
        self::assertNull(SqlTokenStream::tokenize('[unfinished', $standard)->identifierAt());
        self::assertNull(SqlTokenStream::tokenize('""', $allCapabilities)->identifierAt());
        self::assertNull(SqlTokenStream::tokenize('``', $allCapabilities)->identifierAt());
    }

    public function testIdentifierAtUnescapesBracketAndAdvancesPastItsClosingToken(): void
    {
        self::assertSame(
            ['name' => 'a]b', 'next' => 1],
            SqlTokenStream::tokenize('[a]]b] tail', FakeSqlLexerProfiles::allCapabilities())->identifierAt(),
        );
    }

    public function testSplitsOnlyTopLevelStatementTerminators(): void
    {
        $sql = 'SELECT \';\' AS value; SELECT $$a;b$$; /* ; */ SELECT (3; 4); SELECT [5; 6];;';
        $profile = FakeSqlLexerProfiles::allCapabilities();

        self::assertSame(
            ["SELECT ';' AS value", 'SELECT $$a;b$$', '/* ; */ SELECT (3; 4)', 'SELECT [5; 6]'],
            SqlTokenStream::tokenize($sql, $profile)->splitStatements(),
        );
    }

    public function testReturnsNoStatementsForEmptyInputAndTerminators(): void
    {
        self::assertSame([], SqlTokenStream::tokenize(' ; ; ', FakeSqlLexerProfiles::standard())->splitStatements());
    }

    public function testUsesProfileSuppliedNestingAndPunctuation(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            nestingPair: ['{', '}'],
            statementDelimiter: '!',
            listDelimiter: '|',
        );
        $nested = SqlTokenStream::tokenize('outer{inner{value}}', $profile);
        $tokens = $nested->significantTokens();

        self::assertSame('}', $nested->matchingClosingNestingToken($tokens[1])?->text);
        self::assertSame(
            ['alpha{nested!value}', 'beta'],
            SqlTokenStream::tokenize('alpha{nested!value}!beta', $profile)->splitStatements(),
        );
        self::assertSame(
            ['first', 'nested{second|third}', 'last'],
            SqlTokenStream::tokenize('first|nested{second|third}|last', $profile)->splitTopLevel(),
        );
    }

    public function testClauseIgnoresNestedKeywords(): void
    {
        $sql = "UPDATE users SET label = TRIM(BOTH 'x' FROM label), amount = CAST(raw AS DECIMAL(10,2)) WHERE id IN (SELECT id FROM source WHERE active) ORDER BY id";

        $stream = SqlTokenStream::tokenize($sql, FakeSqlLexerProfiles::standard());

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
        $stream = SqlTokenStream::tokenize($sql, FakeSqlLexerProfiles::standard());

        self::assertSame('created_at', $stream->topLevelClause(['ORDER', 'BY'], [['LIMIT'], ['RETURNING']]));
        self::assertSame('id', $stream->topLevelClause(['SELECT'], [['LIMIT'], ['FROM']]));
        self::assertNull($stream->topLevelClause(['GROUP', 'BY'], [['LIMIT']]));
    }

    public function testClauseRejectsSequencesSplitAcrossNestingLevels(): void
    {
        $sql = 'SELECT ORDER (BY hidden) BY visible FROM source';

        self::assertNull(SqlTokenStream::tokenize($sql, FakeSqlLexerProfiles::standard())->topLevelClause(['ORDER', 'BY'], [['FROM']]));
    }

    public function testClauseCanStartAtLastAvailableToken(): void
    {
        self::assertSame('', SqlTokenStream::tokenize('SET', FakeSqlLexerProfiles::standard())->topLevelClause(['SET']));
    }

    public function testClauseAfterAnchorSkipsEarlierMatchingKeyword(): void
    {
        $sql = 'INSERT INTO items SELECT * FROM source WHERE active ON CONFLICT (id) DO UPDATE SET score = excluded.score WHERE items.score >= 80 RETURNING id';

        self::assertSame(
            'items.score >= 80',
            SqlTokenStream::tokenize($sql, FakeSqlLexerProfiles::standard())->topLevelClauseAfter(
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
            SqlTokenStream::tokenize('DO UPDATE SET WHERE earlier WHERE later', FakeSqlLexerProfiles::standard())->topLevelClauseAfter(
                ['DO', 'UPDATE', 'SET'],
                ['WHERE'],
                [['WHERE']],
            ),
        );
    }

    public function testSplitsCommasOutsideParenthesesArraysAndStrings(): void
    {
        $stream = SqlTokenStream::tokenize("ARRAY[1,2], COALESCE(a, b), 'x,y', plain", FakeSqlLexerProfiles::standard());

        self::assertSame(
            ['ARRAY[1,2]', 'COALESCE(a, b)', "'x,y'", 'plain'],
            $stream->splitTopLevel(),
        );
    }

    public function testSplitsWithCustomTopLevelDelimiterAndPreservesEmptyParts(): void
    {
        $stream = SqlTokenStream::tokenize(' first ; (nested ; value) ; ; last ', FakeSqlLexerProfiles::standard());

        self::assertSame(
            ['first', '(nested ; value)', '', 'last'],
            $stream->splitTopLevel(';'),
        );
    }

    public function testSplitTopLevelHandlesEmptyAndTrailingDelimiterInput(): void
    {
        self::assertSame([], SqlTokenStream::tokenize('', FakeSqlLexerProfiles::standard())->splitTopLevel());
        self::assertSame(['value', ''], SqlTokenStream::tokenize('value,', FakeSqlLexerProfiles::standard())->splitTopLevel());
        self::assertSame(['value'], SqlTokenStream::tokenize('value', FakeSqlLexerProfiles::standard())->splitTopLevel());
    }

    public function testTokenizesWhitespaceAndNestedCommentsLosslessly(): void
    {
        $sql = " \t-- note\n/* outer /* inner */ end */x";
        $tokens = SqlTokenStream::tokenize($sql, FakeSqlLexerProfiles::standard())->tokens();

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
            array_map(static fn (SqlToken $token): string => $token->text, SqlTokenStream::tokenize($sql, FakeSqlLexerProfiles::standard())->significantTokens()),
        );
    }

    public function testTokenizesUnterminatedCommentsLosslessly(): void
    {
        self::assertSame(
            [[SqlTokenKind::Comment, '/* outer /* inner */']],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('/* outer /* inner */', FakeSqlLexerProfiles::standard())->tokens(),
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
                SqlTokenStream::tokenize('/**/next/*/unterminated', FakeSqlLexerProfiles::standard())->tokens(),
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
                SqlTokenStream::tokenize("prefix --\nnext", FakeSqlLexerProfiles::standard())->tokens(),
            ),
        );
    }

    public function testAdditionalLineCommentsRemainProfileSpecific(): void
    {
        $sql = "# SELECT hidden\nDELETE FROM users WHERE payload #>> '$.name' = 'Alice'";
        $profile = FakeSqlLexerProfiles::allCapabilities();

        self::assertSame(
            ['DELETE', 'FROM', 'users', 'WHERE', 'payload'],
            array_map(
                static fn (SqlToken $token): string => $token->text,
                SqlTokenStream::tokenize($sql, $profile)->significantTokens(),
            ),
        );
        self::assertSame(
            ['#', 'SELECT', 'hidden', 'DELETE', 'FROM', 'users', 'WHERE', 'payload', '#', '>', '>', "'$.name'", '=', "'Alice'"],
            array_map(
                static fn (SqlToken $token): string => $token->text,
                SqlTokenStream::tokenize($sql, FakeSqlLexerProfiles::standard())->significantTokens(),
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
                SqlTokenStream::tokenize('/*/**/*/tail/*/*/x*/tail', FakeSqlLexerProfiles::standard())->tokens(),
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
                SqlTokenStream::tokenize('value**not_comment', FakeSqlLexerProfiles::standard())->tokens(),
            ),
        );
    }

    public function testTokenizesQuotedValuesAndIdentifiers(): void
    {
        $sql = "'it''s' 'a\\\\\'b' \"a\"\"b\" `a``b` 'open";
        $profile = FakeSqlLexerProfiles::allCapabilities();

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
                SqlTokenStream::tokenize($sql, $profile)->tokens(),
            ),
        );
    }

    public function testIdentifierQuotesDoNotUseStringBackslashEscapes(): void
    {
        self::assertSame(
            [
                [SqlTokenKind::QuotedIdentifier, '"a\\"'],
                [SqlTokenKind::Whitespace, ' '],
                [SqlTokenKind::Word, 'tail'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('"a\\" tail', FakeSqlLexerProfiles::allCapabilities())->tokens(),
            ),
        );
    }

    public function testQuotedEscapeAtEndRemainsInsideUnterminatedValue(): void
    {
        $profile = FakeSqlLexerProfiles::allCapabilities();

        self::assertSame(
            [[SqlTokenKind::String, "'value\x5c"]],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize("'value\x5c", $profile)->tokens(),
            ),
        );
    }

    public function testQuotedEscapeControlsWhereTheValueEnds(): void
    {
        $profile = FakeSqlLexerProfiles::allCapabilities();

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
                SqlTokenStream::tokenize("'''\x5c'x' '\x5cx' tail", $profile)->tokens(),
            ),
        );
    }

    public function testTokenizesDollarStringsAndParameters(): void
    {
        $sql = '$$body$$ $tag$body$tag$ $1 $123 ? :name :: : $word';
        $profile = FakeSqlLexerProfiles::allCapabilities();

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
                SqlTokenStream::tokenize($sql, $profile)->significantTokens(),
            ),
        );
    }

    public function testParametersStopBeforeFollowingWordAndAtEndOfInput(): void
    {
        $profile = FakeSqlLexerProfiles::allCapabilities();

        self::assertSame(
            [
                [SqlTokenKind::Parameter, '$1'],
                [SqlTokenKind::Word, 'x'],
                [SqlTokenKind::Parameter, ':end'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('$1x :end', $profile)->significantTokens(),
            ),
        );
    }

    public function testTokenizesNumberedQuestionMarkAndStructuredNamedParameters(): void
    {
        self::assertSame(
            [
                [SqlTokenKind::Parameter, '?123'],
                [SqlTokenKind::Parameter, ':x::part(payload)'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('?123 :x::part(payload)', FakeSqlLexerProfiles::allCapabilities())->significantTokens(),
            ),
        );
    }

    public function testQuestionMarkProfileCanRejectNumericSuffixes(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            positionalParameterPatterns: ['/^\?/'],
        );

        self::assertSame(
            [
                [SqlTokenKind::Parameter, '?'],
                [SqlTokenKind::Number, '123'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('?123', $profile)->significantTokens(),
            ),
        );
    }

    public function testTokenizesUnterminatedDollarString(): void
    {
        $profile = FakeSqlLexerProfiles::allCapabilities();

        self::assertSame(
            [[SqlTokenKind::String, '$tag$body']],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('$tag$body', $profile)->tokens(),
            ),
        );
    }

    public function testDollarTagMustStartAtCurrentOffset(): void
    {
        $profile = FakeSqlLexerProfiles::allCapabilities();

        self::assertSame(
            [
                [SqlTokenKind::Symbol, '$'],
                [SqlTokenKind::Word, 'bad'],
                [SqlTokenKind::Symbol, '-'],
                [SqlTokenKind::String, '$tag$body$tag$'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize('$bad-$tag$body$tag$', $profile)->tokens(),
            ),
        );
    }

    public function testTokenizesWordsAndConfiguredNumberForms(): void
    {
        $sql = "_word2\$tail café9 \x80 0 9_000 0xCA_FE 0Xb 0y 1.25 2e3 3E+4 4e-5 5. 6eX";
        $profile = FakeSqlLexerProfiles::allCapabilities();

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
                [SqlTokenKind::Number, '6'],
                [SqlTokenKind::Word, 'eX'],
            ],
            array_map(
                static fn (SqlToken $token): array => [$token->kind, $token->text],
                SqlTokenStream::tokenize($sql, $profile)->significantTokens(),
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
                SqlTokenStream::tokenize('([x],(y))[[z]])](', FakeSqlLexerProfiles::standard())->significantTokens(),
            ),
        );
    }

    public function testFindsFirstTopLevelKeywordAfterLeadingComment(): void
    {
        self::assertSame(
            'WITH',
            SqlTokenStream::tokenize('/* SELECT */ WITH data AS (SELECT 1) SELECT * FROM data', FakeSqlLexerProfiles::standard())->firstTopLevelKeyword(),
        );
    }

    public function testFirstTopLevelKeywordIgnoresNestedWordsAndCanBeAbsent(): void
    {
        self::assertSame('UPDATE', SqlTokenStream::tokenize('(SELECT hidden) [DELETE hidden] update users', FakeSqlLexerProfiles::standard())->firstTopLevelKeyword());
        self::assertNull(SqlTokenStream::tokenize("123 + 'SELECT'", FakeSqlLexerProfiles::standard())->firstTopLevelKeyword());
    }

    public function testKeepsArithmeticOperatorsOutsideNumberTokens(): void
    {
        $tokens = SqlTokenStream::tokenize('SELECT 1+2, 3.5e-2', FakeSqlLexerProfiles::standard())->significantTokens();

        self::assertSame(
            ['SELECT', '1', '+', '2', ',', '3.5e-2'],
            array_map(static fn (SqlToken $token): string => $token->text, $tokens),
        );
    }

    public function testTokensKeepsEveryLexemeIncludingTheSpaceBetweenThem(): void
    {
        $stream = SqlTokenStream::tokenize('SELECT 1', FakeSqlLexerProfiles::standard());

        self::assertGreaterThan(count($stream->significantTokens()), count($stream->tokens()));
    }

    public function testSignificantTokensLeavesOutWhitespaceAndComments(): void
    {
        $stream = SqlTokenStream::tokenize('SELECT /* c */ 1', FakeSqlLexerProfiles::standard());

        $texts = array_map(static fn (SqlToken $t): string => $t->text, $stream->significantTokens());

        self::assertSame(['SELECT', '1'], $texts);
    }

    public function testSignificantTokenBeforeAnswersTheLexemeThatCameFirst(): void
    {
        $stream = SqlTokenStream::tokenize('SELECT 1', FakeSqlLexerProfiles::standard());
        $tokens = $stream->significantTokens();

        self::assertSame($tokens[0], $stream->significantTokenBefore($tokens[1]));
    }

    public function testSignificantTokenBeforeIsNothingForTheFirstLexeme(): void
    {
        $stream = SqlTokenStream::tokenize('SELECT 1', FakeSqlLexerProfiles::standard());
        $tokens = $stream->significantTokens();

        self::assertNull($stream->significantTokenBefore($tokens[0]));
    }

    public function testSignificantTokenAfterAnswersTheLexemeThatComesNext(): void
    {
        $stream = SqlTokenStream::tokenize('SELECT 1', FakeSqlLexerProfiles::standard());
        $tokens = $stream->significantTokens();

        self::assertSame($tokens[1], $stream->significantTokenAfter($tokens[0]));
    }

    public function testSignificantTokenAfterIsNothingForTheLastLexeme(): void
    {
        $stream = SqlTokenStream::tokenize('SELECT 1', FakeSqlLexerProfiles::standard());
        $tokens = $stream->significantTokens();

        self::assertNull($stream->significantTokenAfter($tokens[count($tokens) - 1]));
    }

    public function testMatchingClosingNestingTokenAnswersTheParenthesisThatClosesOne(): void
    {
        $stream = SqlTokenStream::tokenize('SELECT (1 + (2))', FakeSqlLexerProfiles::standard());
        $tokens = $stream->significantTokens();
        $opening = null;
        foreach ($tokens as $token) {
            if ($token->text === '(') {
                $opening = $token;
                break;
            }
        }

        self::assertNotNull($opening);
        $closing = $stream->matchingClosingNestingToken($opening);
        self::assertNotNull($closing);
        self::assertSame(')', $closing->text);
        self::assertSame(15, $closing->offset);
    }

    public function testMatchingClosingNestingTokenIsNothingForALexemeThatOpensNothing(): void
    {
        $stream = SqlTokenStream::tokenize('SELECT 1', FakeSqlLexerProfiles::standard());
        $tokens = $stream->significantTokens();

        self::assertNull($stream->matchingClosingNestingToken($tokens[0]));
    }

    public function testSplitStatementsReadsABatchAsTheStatementsItIsWrittenAs(): void
    {
        $stream = SqlTokenStream::tokenize('SELECT 1; SELECT 2', FakeSqlLexerProfiles::standard());

        self::assertSame(['SELECT 1', 'SELECT 2'], array_map(trim(...), $stream->splitStatements()));
    }

    public function testSplitStatementsLeavesASemicolonInsideAStringAlone(): void
    {
        $stream = SqlTokenStream::tokenize("SELECT ';'", FakeSqlLexerProfiles::standard());

        self::assertCount(1, $stream->splitStatements());
    }

    public function testTopLevelClauseAnswersTheTextBetweenTheKeywordsThatBoundIt(): void
    {
        $stream = SqlTokenStream::tokenize(
            'SELECT * FROM users WHERE id = 1 ORDER BY id',
            FakeSqlLexerProfiles::standard(),
        );

        self::assertSame('id = 1', trim((string) $stream->topLevelClause(['WHERE'], [['ORDER']])));
    }

    public function testTopLevelClauseIsNothingWhereTheKeywordIsNotWritten(): void
    {
        $stream = SqlTokenStream::tokenize('SELECT 1', FakeSqlLexerProfiles::standard());

        self::assertNull($stream->topLevelClause(['WHERE']));
    }

    public function testTopLevelClauseAfterLooksOnlyPastTheAnchorItWasGiven(): void
    {
        $stream = SqlTokenStream::tokenize(
            'UPDATE users SET name = \'a\' WHERE id = 1',
            FakeSqlLexerProfiles::standard(),
        );

        self::assertSame('id = 1', trim((string) $stream->topLevelClauseAfter(['SET'], ['WHERE'], [])));
    }

    public function testTopLevelClauseAfterIsNothingWhereTheAnchorIsNotWritten(): void
    {
        $stream = SqlTokenStream::tokenize('SELECT 1 WHERE 1', FakeSqlLexerProfiles::standard());

        self::assertNull($stream->topLevelClauseAfter(['SET'], ['WHERE'], []));
    }
}
