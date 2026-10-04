<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Leaf\CharsetRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(CharsetRule::class)]
#[Medium]
final class CharsetRuleTest extends TestCase
{
    public function testNameLowersANamedBinaryOrDefaultCharacterSet(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $rule = new CharsetRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $text = new Node('ident_or_text', 0, [new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', 'utf8mb4', 0)])])]);

        self::assertSame('utf8mb4', $rule->name(new Node('charset_name_or_default', 0, [new Node('charset_name', 0, [$text])]))?->value);
        self::assertSame('binary', $rule->name(new Node('charset_name', 1, [new Token(0, 'BINARY', 'BINARY', 0)]))?->value);
        self::assertNull($rule->name(new Node('charset_name_or_default', 1, [new Token(0, 'DEFAULT', 'DEFAULT', 0)])));
        self::assertSame('utf8mb4', $rule->name(new Node('old_or_new_charset_name_or_default', 0, [new Node('old_or_new_charset_name', 0, [$text])]))?->value);
        self::assertNull($rule->name(new Node('collation_name_or_default', 1, [new Token(0, 'DEFAULT', 'DEFAULT', 0)])));
    }

    public function testCharsetLowersADefaultCharsetClause(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new CharsetRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $text = new Node('ident_or_text', 0, [new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', 'latin1', 0)])])]);
        $clause = new Node('default_charset', 0, [new Node('opt_default', 1, [new Token(0, 'DEFAULT_SYM', 'DEFAULT', 0)]), new Node('character_set', 1, [new Token(0, 'CHARSET', 'CHARSET', 0)]), new Node('opt_equal', 0, []), new Node('charset_name', 0, [$text])]);

        self::assertSame('latin1', $rule->charset($clause)->name?->value);
        self::assertSame('latin1', $rule->charset(new Node('charset_name', 0, [$text]))->name?->value);
    }

    public function testCollationLowersEveryCollationClause(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new CharsetRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $text = new Node('ident_or_text', 0, [new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', 'utf8mb4_bin', 0)])])]);
        $collate = new Token(0, 'COLLATE_SYM', 'COLLATE', 0);
        $clause = new Node('default_collation', 0, [new Node('opt_default', 0, []), $collate, new Node('opt_equal', 0, []), new Node('collation_name', 0, [$text])]);

        self::assertNull($rule->collation(new Node('opt_collate', 0, [])));
        self::assertSame('utf8mb4_bin', $rule->collation(new Node('opt_collate', 1, [$collate, new Node('collation_name', 0, [$text])]))?->name?->value);
        self::assertSame('utf8mb4_bin', $rule->collation($clause)?->name?->value);
        self::assertSame('binary', $rule->collation(new Node('collation_name', 1, [new Token(0, 'BINARY_SYM', 'BINARY', 0)]))?->name?->value);
    }
}
