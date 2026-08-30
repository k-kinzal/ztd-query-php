<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\Lexical\SqliteCoverageSamples;

#[CoversClass(SqliteCoverageSamples::class)]
final class SqliteCoverageSamplesTest extends TestCase
{
    public function testAllAnswersEverySampleWithTheTokensAndClassesItStandsFor(): void
    {
        self::assertSame(
            [
            'cc-x' => ["X'00'", ['TK_BLOB'], ['CC_X']],
            'cc-keyword-start' => ['SELECT', ['TK_SELECT'], ['CC_KYWD0']],
            'cc-keyword' => ['z', ['TK_ID'], ['CC_KYWD']],
            'cc-digit' => ['1.5', ['TK_FLOAT'], ['CC_DIGIT']],
            'cc-dollar' => ['$name', ['TK_VARIABLE'], ['CC_DOLLAR']],
            'cc-variable-alpha' => [':name', ['TK_VARIABLE'], ['CC_VARALPHA']],
            'cc-variable-number' => ['?1', ['TK_VARIABLE'], ['CC_VARNUM']],
            'cc-space' => [" \t\n", ['TK_SPACE'], ['CC_SPACE']],
            'cc-quote' => ["'text' \"name\" `name`", ['TK_STRING', 'TK_SPACE', 'TK_ID', 'TK_SPACE', 'TK_ID'], ['CC_QUOTE', 'CC_SPACE']],
            'cc-bracket' => ['[name]', ['TK_ID'], ['CC_QUOTE2']],
            'cc-pipe' => ['| ||', ['TK_BITOR', 'TK_SPACE', 'TK_CONCAT'], ['CC_PIPE', 'CC_SPACE']],
            'cc-minus' => ["- -- comment\n", ['TK_MINUS', 'TK_SPACE', 'TK_SPACE', 'TK_SPACE'], ['CC_MINUS', 'CC_SPACE']],
            'cc-less' => ['< <= <> <<', ['TK_LT', 'TK_SPACE', 'TK_LE', 'TK_SPACE', 'TK_NE', 'TK_SPACE', 'TK_LSHIFT'], ['CC_LT', 'CC_SPACE']],
            'cc-greater' => ['> >= >>', ['TK_GT', 'TK_SPACE', 'TK_GE', 'TK_SPACE', 'TK_RSHIFT'], ['CC_GT', 'CC_SPACE']],
            'cc-equal' => ['= ==', ['TK_EQ', 'TK_SPACE', 'TK_EQ'], ['CC_EQ', 'CC_SPACE']],
            'cc-bang' => ['!=', ['TK_NE'], ['CC_BANG']],
            'cc-slash' => ['/ /* comment */', ['TK_SLASH', 'TK_SPACE', 'TK_SPACE'], ['CC_SLASH', 'CC_SPACE']],
            'cc-left-parenthesis' => ['(', ['TK_LP'], ['CC_LP']],
            'cc-right-parenthesis' => [')', ['TK_RP'], ['CC_RP']],
            'cc-semicolon' => [';', ['TK_SEMI'], ['CC_SEMI']],
            'cc-plus' => ['+', ['TK_PLUS'], ['CC_PLUS']],
            'cc-star' => ['*', ['TK_STAR'], ['CC_STAR']],
            'cc-percent' => ['%', ['TK_REM'], ['CC_PERCENT']],
            'cc-comma' => [',', ['TK_COMMA'], ['CC_COMMA']],
            'cc-and' => ['&', ['TK_BITAND'], ['CC_AND']],
            'cc-tilde' => ['~', ['TK_BITNOT'], ['CC_TILDA']],
            'cc-dot' => ['. .5', ['TK_DOT', 'TK_SPACE', 'TK_FLOAT'], ['CC_DOT', 'CC_SPACE']],
            'cc-id' => ['é', ['TK_ID'], ['CC_ID']],
            'cc-illegal' => ["\x01", ['TK_ILLEGAL'], ['CC_ILLEGAL']],
            'cc-bom' => ["\xef\xbb\xbf", ['TK_SPACE'], ['CC_BOM']],
            ],
            (new SqliteCoverageSamples())->all(),
        );
    }

    public function testUnreachableSaysWhichClassNoSqlCanShowAndWhy(): void
    {
        self::assertSame(
            [
                'CC_NUL' => 'NUL terminates SQLite input and cannot be emitted inside a generated SQL statement.',
            ],
            (new SqliteCoverageSamples())->unreachable(),
        );
    }
}
