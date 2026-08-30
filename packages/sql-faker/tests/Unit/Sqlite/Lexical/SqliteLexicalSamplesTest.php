<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\Lexical\SqliteLexicalSamples;

#[CoversClass(SqliteLexicalSamples::class)]
final class SqliteLexicalSamplesTest extends TestCase
{
    public function testIdentifierSamplesCoversEveryWayAnIdentifierIsQuoted(): void
    {
        self::assertSame(
            [
                ['name', ['TK_ID'], ['CC_KYWD0']],
                ['"select"', ['TK_ID'], ['CC_QUOTE']],
                ['`select`', ['TK_ID'], ['CC_QUOTE']],
                ['[select]', ['TK_ID'], ['CC_QUOTE2']],
            ],
            (new SqliteLexicalSamples())->identifierSamples(),
        );
    }

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
        0 => 'TK_SPACE',
      ],
      2 =>
       [
        0 => 'CC_SPACE',
      ],
    ],
    1 =>
     [
      0 => '/* comment */',
      1 =>
       [
        0 => 'TK_SPACE',
      ],
      2 =>
       [
        0 => 'CC_SLASH',
      ],
    ],
    2 =>
     [
      0 => '-- comment
',
      1 =>
       [
        0 => 'TK_SPACE',
        1 => 'TK_SPACE',
      ],
      2 =>
       [
        0 => 'CC_MINUS',
        1 => 'CC_SPACE',
      ],
    ],
  ],
  'ID' =>
   [
    0 =>
     [
      0 => 'name',
      1 =>
       [
        0 => 'TK_ID',
      ],
      2 =>
       [
        0 => 'CC_KYWD0',
      ],
    ],
    1 =>
     [
      0 => '"select"',
      1 =>
       [
        0 => 'TK_ID',
      ],
      2 =>
       [
        0 => 'CC_QUOTE',
      ],
    ],
    2 =>
     [
      0 => '`select`',
      1 =>
       [
        0 => 'TK_ID',
      ],
      2 =>
       [
        0 => 'CC_QUOTE',
      ],
    ],
    3 =>
     [
      0 => '[select]',
      1 =>
       [
        0 => 'TK_ID',
      ],
      2 =>
       [
        0 => 'CC_QUOTE2',
      ],
    ],
  ],
  'id' =>
   [
    0 =>
     [
      0 => 'name',
      1 =>
       [
        0 => 'TK_ID',
      ],
      2 =>
       [
        0 => 'CC_KYWD0',
      ],
    ],
    1 =>
     [
      0 => '"select"',
      1 =>
       [
        0 => 'TK_ID',
      ],
      2 =>
       [
        0 => 'CC_QUOTE',
      ],
    ],
    2 =>
     [
      0 => '`select`',
      1 =>
       [
        0 => 'TK_ID',
      ],
      2 =>
       [
        0 => 'CC_QUOTE',
      ],
    ],
    3 =>
     [
      0 => '[select]',
      1 =>
       [
        0 => 'TK_ID',
      ],
      2 =>
       [
        0 => 'CC_QUOTE2',
      ],
    ],
  ],
  'idj' =>
   [
    0 =>
     [
      0 => 'name',
      1 =>
       [
        0 => 'TK_ID',
      ],
      2 =>
       [
        0 => 'CC_KYWD0',
      ],
    ],
    1 =>
     [
      0 => '"select"',
      1 =>
       [
        0 => 'TK_ID',
      ],
      2 =>
       [
        0 => 'CC_QUOTE',
      ],
    ],
    2 =>
     [
      0 => '`select`',
      1 =>
       [
        0 => 'TK_ID',
      ],
      2 =>
       [
        0 => 'CC_QUOTE',
      ],
    ],
    3 =>
     [
      0 => '[select]',
      1 =>
       [
        0 => 'TK_ID',
      ],
      2 =>
       [
        0 => 'CC_QUOTE2',
      ],
    ],
  ],
  'ids' =>
   [
    0 =>
     [
      0 => '\'text\'',
      1 =>
       [
        0 => 'TK_STRING',
      ],
      2 =>
       [
        0 => 'CC_QUOTE',
      ],
    ],
    1 =>
     [
      0 => '\'a\'\'b\'',
      1 =>
       [
        0 => 'TK_STRING',
      ],
      2 =>
       [
        0 => 'CC_QUOTE',
      ],
    ],
  ],
  'STRING' =>
   [
    0 =>
     [
      0 => '\'text\'',
      1 =>
       [
        0 => 'TK_STRING',
      ],
      2 =>
       [
        0 => 'CC_QUOTE',
      ],
    ],
    1 =>
     [
      0 => '\'/* not a comment */\'',
      1 =>
       [
        0 => 'TK_STRING',
      ],
      2 =>
       [
        0 => 'CC_QUOTE',
      ],
    ],
    2 =>
     [
      0 => '\'a\'\'b\'',
      1 =>
       [
        0 => 'TK_STRING',
      ],
      2 =>
       [
        0 => 'CC_QUOTE',
      ],
    ],
  ],
  'BLOB' =>
   [
    0 =>
     [
      0 => 'X\'00ff\'',
      1 =>
       [
        0 => 'TK_BLOB',
      ],
      2 =>
       [
        0 => 'CC_X',
      ],
    ],
  ],
  'number' =>
   [
    0 =>
     [
      0 => '1',
      1 =>
       [
        0 => 'TK_INTEGER',
      ],
      2 =>
       [
        0 => 'CC_DIGIT',
      ],
    ],
    1 =>
     [
      0 => '1.5',
      1 =>
       [
        0 => 'TK_FLOAT',
      ],
      2 =>
       [
        0 => 'CC_DIGIT',
      ],
    ],
    2 =>
     [
      0 => '.5',
      1 =>
       [
        0 => 'TK_FLOAT',
      ],
      2 =>
       [
        0 => 'CC_DOT',
      ],
    ],
    3 =>
     [
      0 => '1e2',
      1 =>
       [
        0 => 'TK_FLOAT',
      ],
      2 =>
       [
        0 => 'CC_DIGIT',
      ],
    ],
  ],
  'INTEGER' =>
   [
    0 =>
     [
      0 => '1',
      1 =>
       [
        0 => 'TK_INTEGER',
      ],
      2 =>
       [
        0 => 'CC_DIGIT',
      ],
    ],
    1 =>
     [
      0 => '0x10',
      1 =>
       [
        0 => 'TK_INTEGER',
      ],
      2 =>
       [
        0 => 'CC_DIGIT',
      ],
    ],
  ],
  'QNUMBER' =>
   [
    0 =>
     [
      0 => '1_0',
      1 =>
       [
        0 => 'TK_QNUMBER',
      ],
      2 =>
       [
        0 => 'CC_DIGIT',
      ],
    ],
  ],
  'VARIABLE' =>
   [
    0 =>
     [
      0 => '?',
      1 =>
       [
        0 => 'TK_VARIABLE',
      ],
      2 =>
       [
        0 => 'CC_VARNUM',
      ],
    ],
    1 =>
     [
      0 => '?1',
      1 =>
       [
        0 => 'TK_VARIABLE',
      ],
      2 =>
       [
        0 => 'CC_VARNUM',
      ],
    ],
    2 =>
     [
      0 => ':name',
      1 =>
       [
        0 => 'TK_VARIABLE',
      ],
      2 =>
       [
        0 => 'CC_VARALPHA',
      ],
    ],
    3 =>
     [
      0 => '@name',
      1 =>
       [
        0 => 'TK_VARIABLE',
      ],
      2 =>
       [
        0 => 'CC_VARALPHA',
      ],
    ],
    4 =>
     [
      0 => '$name',
      1 =>
       [
        0 => 'TK_VARIABLE',
      ],
      2 =>
       [
        0 => 'CC_DOLLAR',
      ],
    ],
  ],
  'ANY' =>
   [
    0 =>
     [
      0 => 'name',
      1 =>
       [
        0 => 'TK_ID',
      ],
      2 =>
       [
        0 => 'CC_KYWD0',
      ],
    ],
  ],
  'LP' =>
   [
    0 =>
     [
      0 => '(',
      1 =>
       [
        0 => 'TK_LP',
      ],
      2 =>
       [
        0 => 'CC_LP',
      ],
    ],
  ],
  'RP' =>
   [
    0 =>
     [
      0 => ')',
      1 =>
       [
        0 => 'TK_RP',
      ],
      2 =>
       [
        0 => 'CC_RP',
      ],
    ],
  ],
  'SEMI' =>
   [
    0 =>
     [
      0 => ';',
      1 =>
       [
        0 => 'TK_SEMI',
      ],
      2 =>
       [
        0 => 'CC_SEMI',
      ],
    ],
  ],
  'COMMA' =>
   [
    0 =>
     [
      0 => ',',
      1 =>
       [
        0 => 'TK_COMMA',
      ],
      2 =>
       [
        0 => 'CC_COMMA',
      ],
    ],
  ],
  'DOT' =>
   [
    0 =>
     [
      0 => '.',
      1 =>
       [
        0 => 'TK_DOT',
      ],
      2 =>
       [
        0 => 'CC_DOT',
      ],
    ],
  ],
  'EQ' =>
   [
    0 =>
     [
      0 => '=',
      1 =>
       [
        0 => 'TK_EQ',
      ],
      2 =>
       [
        0 => 'CC_EQ',
      ],
    ],
    1 =>
     [
      0 => '==',
      1 =>
       [
        0 => 'TK_EQ',
      ],
      2 =>
       [
        0 => 'CC_EQ',
      ],
    ],
  ],
  'LT' =>
   [
    0 =>
     [
      0 => '<',
      1 =>
       [
        0 => 'TK_LT',
      ],
      2 =>
       [
        0 => 'CC_LT',
      ],
    ],
  ],
  'LE' =>
   [
    0 =>
     [
      0 => '<=',
      1 =>
       [
        0 => 'TK_LE',
      ],
      2 =>
       [
        0 => 'CC_LT',
      ],
    ],
  ],
  'GT' =>
   [
    0 =>
     [
      0 => '>',
      1 =>
       [
        0 => 'TK_GT',
      ],
      2 =>
       [
        0 => 'CC_GT',
      ],
    ],
  ],
  'GE' =>
   [
    0 =>
     [
      0 => '>=',
      1 =>
       [
        0 => 'TK_GE',
      ],
      2 =>
       [
        0 => 'CC_GT',
      ],
    ],
  ],
  'NE' =>
   [
    0 =>
     [
      0 => '<>',
      1 =>
       [
        0 => 'TK_NE',
      ],
      2 =>
       [
        0 => 'CC_LT',
      ],
    ],
    1 =>
     [
      0 => '!=',
      1 =>
       [
        0 => 'TK_NE',
      ],
      2 =>
       [
        0 => 'CC_BANG',
      ],
    ],
  ],
  'PLUS' =>
   [
    0 =>
     [
      0 => '+',
      1 =>
       [
        0 => 'TK_PLUS',
      ],
      2 =>
       [
        0 => 'CC_PLUS',
      ],
    ],
  ],
  'MINUS' =>
   [
    0 =>
     [
      0 => '-',
      1 =>
       [
        0 => 'TK_MINUS',
      ],
      2 =>
       [
        0 => 'CC_MINUS',
      ],
    ],
  ],
  'STAR' =>
   [
    0 =>
     [
      0 => '*',
      1 =>
       [
        0 => 'TK_STAR',
      ],
      2 =>
       [
        0 => 'CC_STAR',
      ],
    ],
  ],
  'SLASH' =>
   [
    0 =>
     [
      0 => '/',
      1 =>
       [
        0 => 'TK_SLASH',
      ],
      2 =>
       [
        0 => 'CC_SLASH',
      ],
    ],
  ],
  'REM' =>
   [
    0 =>
     [
      0 => '%',
      1 =>
       [
        0 => 'TK_REM',
      ],
      2 =>
       [
        0 => 'CC_PERCENT',
      ],
    ],
  ],
  'BITAND' =>
   [
    0 =>
     [
      0 => '&',
      1 =>
       [
        0 => 'TK_BITAND',
      ],
      2 =>
       [
        0 => 'CC_AND',
      ],
    ],
  ],
  'BITOR' =>
   [
    0 =>
     [
      0 => '|',
      1 =>
       [
        0 => 'TK_BITOR',
      ],
      2 =>
       [
        0 => 'CC_PIPE',
      ],
    ],
  ],
  'BITNOT' =>
   [
    0 =>
     [
      0 => '~',
      1 =>
       [
        0 => 'TK_BITNOT',
      ],
      2 =>
       [
        0 => 'CC_TILDA',
      ],
    ],
  ],
  'LSHIFT' =>
   [
    0 =>
     [
      0 => '<<',
      1 =>
       [
        0 => 'TK_LSHIFT',
      ],
      2 =>
       [
        0 => 'CC_LT',
      ],
    ],
  ],
  'RSHIFT' =>
   [
    0 =>
     [
      0 => '>>',
      1 =>
       [
        0 => 'TK_RSHIFT',
      ],
      2 =>
       [
        0 => 'CC_GT',
      ],
    ],
  ],
  'CONCAT' =>
   [
    0 =>
     [
      0 => '||',
      1 =>
       [
        0 => 'TK_CONCAT',
      ],
      2 =>
       [
        0 => 'CC_PIPE',
      ],
    ],
  ],
  'PTR' =>
   [
    0 =>
     [
      0 => '->',
      1 =>
       [
        0 => 'TK_PTR',
      ],
      2 =>
       [
        0 => 'CC_MINUS',
      ],
    ],
    1 =>
     [
      0 => '->>',
      1 =>
       [
        0 => 'TK_PTR',
      ],
      2 =>
       [
        0 => 'CC_MINUS',
      ],
    ],
  ],
  'FLOAT' =>
   [
    0 =>
     [
      0 => '1.5',
      1 =>
       [
        0 => 'TK_FLOAT',
      ],
      2 =>
       [
        0 => 'CC_DIGIT',
      ],
    ],
    1 =>
     [
      0 => '.5',
      1 =>
       [
        0 => 'TK_FLOAT',
      ],
      2 =>
       [
        0 => 'CC_DOT',
      ],
    ],
  ],
],
            (new SqliteLexicalSamples())->all(),
        );
    }
}
