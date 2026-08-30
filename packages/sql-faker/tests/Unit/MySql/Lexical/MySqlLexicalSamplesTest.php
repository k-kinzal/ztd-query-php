<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Lexical\MySqlLexicalSamples;

#[CoversClass(MySqlLexicalSamples::class)]
final class MySqlLexicalSamplesTest extends TestCase
{
    public function testAllAnswersEverySampleAndTheTerminalItStandsFor(): void
    {
        self::assertSame(
            [
  '@TRIVIA' =>
   [
0 =>
 [
  0 => ' ',
  1 =>
   [
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_SKIP',
  ],
],
1 =>
 [
  0 => ' -- comment
 ',
  1 =>
   [
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_COMMENT',
  ],
],
2 =>
 [
  0 => ' # comment
 ',
  1 =>
   [
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_COMMENT',
  ],
],
3 =>
 [
  0 => ' /* comment */ ',
  1 =>
   [
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_LONG_COMMENT',
  ],
],
  ],
  'IDENT' =>
   [
0 =>
 [
  0 => '_sqlfaker_identifier',
  1 =>
   [
    0 => 'IDENT',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_IDENT',
  ],
],
  ],
  'IDENT_QUOTED' =>
   [
0 =>
 [
  0 => '`select`',
  1 =>
   [
    0 => 'IDENT_QUOTED',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_USER_VARIABLE_DELIMITER',
  ],
],
  ],
  'TEXT_STRING' =>
   [
0 =>
 [
  0 => '\'text\'',
  1 =>
   [
    0 => 'TEXT_STRING',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_STRING',
  ],
],
1 =>
 [
  0 => '\'a\'\'b\'',
  1 =>
   [
    0 => 'TEXT_STRING',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_STRING',
  ],
],
  ],
  'NCHAR_STRING' =>
   [
0 =>
 [
  0 => 'N\'text\'',
  1 =>
   [
    0 => 'NCHAR_STRING',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_IDENT_OR_NCHAR',
  ],
],
  ],
  'NUM' =>
   [
0 =>
 [
  0 => '1',
  1 =>
   [
    0 => 'NUM',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_NUMBER_IDENT',
    2 => 'MY_LEX_INT_OR_REAL',
  ],
],
  ],
  'LONG_NUM' =>
   [
0 =>
 [
  0 => '2147483648',
  1 =>
   [
    0 => 'LONG_NUM',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_NUMBER_IDENT',
    2 => 'MY_LEX_INT_OR_REAL',
  ],
],
  ],
  'ULONGLONG_NUM' =>
   [
0 =>
 [
  0 => '18446744073709551615',
  1 =>
   [
    0 => 'ULONGLONG_NUM',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_NUMBER_IDENT',
    2 => 'MY_LEX_INT_OR_REAL',
  ],
],
  ],
  'DECIMAL_NUM' =>
   [
0 =>
 [
  0 => '1.5',
  1 =>
   [
    0 => 'DECIMAL_NUM',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_NUMBER_IDENT',
    2 => 'MY_LEX_REAL',
  ],
],
  ],
  'FLOAT_NUM' =>
   [
0 =>
 [
  0 => '1e2',
  1 =>
   [
    0 => 'FLOAT_NUM',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_NUMBER_IDENT',
    2 => 'MY_LEX_INT_OR_REAL',
  ],
],
  ],
  'HEX_NUM' =>
   [
0 =>
 [
  0 => '0x0f',
  1 =>
   [
    0 => 'HEX_NUM',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_NUMBER_IDENT',
  ],
],
1 =>
 [
  0 => 'X\'0f\'',
  1 =>
   [
    0 => 'HEX_NUM',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_IDENT_OR_HEX',
    2 => 'MY_LEX_HEX_NUMBER',
  ],
],
  ],
  'BIN_NUM' =>
   [
0 =>
 [
  0 => '0b01',
  1 =>
   [
    0 => 'BIN_NUM',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_NUMBER_IDENT',
  ],
],
1 =>
 [
  0 => 'B\'01\'',
  1 =>
   [
    0 => 'BIN_NUM',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_IDENT_OR_BIN',
    2 => 'MY_LEX_BIN_NUMBER',
  ],
],
  ],
  'LEX_HOSTNAME' =>
   [
0 =>
 [
  0 => 'localhost',
  1 =>
   [
    0 => 'LEX_HOSTNAME',
  ],
  2 =>
   [
    0 => 'MY_LEX_HOSTNAME',
  ],
  3 => '@localhost',
],
  ],
  'PARAM_MARKER' =>
   [
0 =>
 [
  0 => '?',
  1 =>
   [
    0 => 'PARAM_MARKER',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_CHAR',
  ],
],
  ],
  'OR2_SYM' =>
   [
0 =>
 [
  0 => '||',
  1 =>
   [
    0 => 'OR2_SYM',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_BOOL',
  ],
],
  ],
  'WITH_ROLLUP_SYM' =>
   [
0 =>
 [
  0 => 'WITH ROLLUP',
  1 =>
   [
    0 => 'WITH_ROLLUP_SYM',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_IDENT',
  ],
],
  ],
  'UNDERSCORE_CHARSET' =>
   [
0 =>
 [
  0 => '_utf8mb4',
  1 =>
   [
    0 => 'UNDERSCORE_CHARSET',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_IDENT',
  ],
],
  ],
  'SET_VAR' =>
   [
0 =>
 [
  0 => ':=',
  1 =>
   [
    0 => 'SET_VAR',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_SET_VAR',
  ],
],
  ],
  'JSON_SEPARATOR_SYM' =>
   [
0 =>
 [
  0 => '->',
  1 =>
   [
    0 => 'JSON_SEPARATOR_SYM',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_CHAR',
  ],
],
  ],
  'JSON_UNQUOTED_SEPARATOR_SYM' =>
   [
0 =>
 [
  0 => '->>',
  1 =>
   [
    0 => 'JSON_UNQUOTED_SEPARATOR_SYM',
  ],
  2 =>
   [
    0 => 'MY_LEX_START',
    1 => 'MY_LEX_CHAR',
  ],
],
  ],
],
            (new MySqlLexicalSamples())->all(),
        );
    }
}
