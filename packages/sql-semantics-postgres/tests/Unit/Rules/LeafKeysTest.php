<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Platform\PostgreSql\Rules\LeafKeys;

#[CoversClass(LeafKeys::class)]
#[Small]
final class LeafKeysTest extends TestCase
{
    public function testKeyDecodesIdentifiersAndKeywordsUsedAsNames(): void
    {
        self::assertSame('name:foo', (new LeafKeys())->key(new Token(1, 'IDENT', '"foo"', 0), 'ColId: IDENT', 0));
        self::assertSame('name:select', (new LeafKeys())->key(new Token(1, 'SELECT', 'Select', 0), 'reserved_keyword: SELECT', 0));
        self::assertSame('SELECT', (new LeafKeys())->key(new Token(1, 'SELECT', 'select', 0), 'simple_select: SELECT opt_all_clause opt_target_list into_clause from_clause where_clause group_clause having_clause window_clause', 0));
    }

    public function testKeyDecodesConstants(): void
    {
        self::assertSame("string:a'b", (new LeafKeys())->key(new Token(1, 'SCONST', "E'a\\'b'", 0), 'Sconst: SCONST', 0));
        self::assertSame('bits:x1F', (new LeafKeys())->key(new Token(1, 'XCONST', "X'1F'", 0), 'AexprConst: XCONST', 0));
        self::assertSame('number:31', (new LeafKeys())->key(new Token(1, 'ICONST', '0x1F', 0), 'Iconst: ICONST', 0));
        self::assertSame('param:$7', (new LeafKeys())->key(new Token(1, 'PARAM', '$007', 0), 'c_expr: PARAM opt_indirection', 0));
        self::assertSame('op:@>', (new LeafKeys())->key(new Token(1, 'Op', '@>', 0), 'all_Op: Op', 0));
    }

    public function testKeyGivesALookaheadTerminalTheKeyOfItsKeyword(): void
    {
        self::assertSame('WITH', (new LeafKeys())->key(new Token(1, 'WITH_LA', 'with', 0), 'opt_timezone: WITH_LA TIME ZONE', 0));
        self::assertSame('NOT_EQUALS', (new LeafKeys())->key(new Token(1, 'NOT_EQUALS', '!=', 0), 'MathOp: NOT_EQUALS', 0));
    }

    public function testKeyIsNullForNoiseAndForTheEndMarker(): void
    {
        self::assertNull((new LeafKeys())->key(new Token(1, ';', ';', 0), 'stmtmulti: stmtmulti ; toplevel_stmt', 1));
        self::assertNull((new LeafKeys())->key(new Token(0, '$end', '', 0), 'parse_toplevel: stmtmulti', 1));
    }

    public function testKeyKeepsTheExactTextOfANumericConstant(): void
    {
        self::assertSame('float:1.50E-03', (new LeafKeys())->key(new Token(1, 'FCONST', '1.50E-03', 0), 'AexprConst: FCONST', 0));
        self::assertSame('float:0x1FFFFFFFFF', (new LeafKeys())->key(new Token(1, 'FCONST', '0x1FFFFFFFFF', 0), 'NumericOnly: FCONST', 0));
        self::assertNotSame((new LeafKeys())->key(new Token(1, 'FCONST', '1e2', 0), 'NumericOnly: FCONST', 0), (new LeafKeys())->key(new Token(1, 'FCONST', '100.', 0), 'NumericOnly: FCONST', 0));
    }

    public function testNoiseMergesTheFamilyTables(): void
    {
        self::assertSame([1], (new LeafKeys())->noise()['target_el: a_expr AS ColLabel']);
        self::assertSame([0, 1], (new LeafKeys())->noise()['opt_set_data: SET DATA_P']);
    }
}
