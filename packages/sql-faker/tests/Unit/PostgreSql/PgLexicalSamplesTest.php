<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSql\PgLexicalSamples;

#[CoversClass(PgLexicalSamples::class)]
final class PgLexicalSamplesTest extends TestCase
{
    public function testAllAnswersEverySampleAndTheTerminalItStandsFor(): void
    {
        self::assertSame(
            [
  '@TRIVIA' =>
   [
0 => ' ',
1 => '	
',
2 => '-- comment
',
3 => '/* outer /* inner */ outer */',
4 => '/* /+ ** */',
  ],
  'IDENT' =>
   [
0 => '_sqlfaker_identifier',
1 => '"select"',
2 => 'U&"select"',
3 => '"a""b"',
  ],
  'SCONST' =>
   [
0 => '\'text\'',
1 => '\'a\'\'b\'',
2 => '\'first\'
\'second\'',
3 => '\'text\' ',
4 => 'E\'a\\\\b\'',
5 => 'E\'\\u0041\'',
6 => 'E\'\\uD800\\uDC00\'',
7 => 'E\'\\101\'',
8 => 'E\'\\x41\'',
9 => 'U&\'text\'',
10 => '$$text$$',
11 => '$tag$text$tag$',
  ],
  'ICONST' =>
   [
0 => '1',
1 => '0x10',
2 => '0o10',
3 => '0b10',
  ],
  'FCONST' =>
   [
0 => '1.5',
1 => '.5',
2 => '1e2',
  ],
  'BCONST' =>
   [
0 => 'B\'01\'',
  ],
  'XCONST' =>
   [
0 => 'X\'0f\'',
  ],
  'Op' =>
   [
0 => '?',
1 => '?|',
2 => '?&',
3 => '@@',
  ],
  'PARAM' =>
   [
0 => '$1',
  ],
  'TYPECAST' =>
   [
0 => '::',
  ],
  'COLON_EQUALS' =>
   [
0 => ':=',
  ],
  'EQUALS_GREATER' =>
   [
0 => '=>',
  ],
  'NOT_EQUALS' =>
   [
0 => '<>',
1 => '!=',
  ],
  'LESS_EQUALS' =>
   [
0 => '<=',
  ],
  'GREATER_EQUALS' =>
   [
0 => '>=',
  ],
  'DOT_DOT' =>
   [
0 => '..',
  ],
],
            (new PgLexicalSamples())->all(),
        );
    }

    public function testRuleWitnessesMapsSuccessfulRulesAndOmitsErrorOnlyRules(): void
    {
        $rules = (new PgLexicalSamples())->ruleWitnesses();

        self::assertSame('postgresql.lookahead.FORMAT_LA', $rules[1]);
        self::assertSame('postgresql.coverage.numeric-range', $rules[65]);
        self::assertSame('postgresql.coverage.other-character', $rules[72]);
        self::assertArrayNotHasKey(25, $rules);
        self::assertArrayNotHasKey(70, $rules);
    }
}
