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
use SqlSemantics\Platform\MySql\Lowering\Leaf\OptionRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior;

#[CoversClass(OptionRule::class)]
#[Medium]
final class OptionRuleTest extends TestCase
{
    public function testPresentTellsWhetherAnOptionalKeywordIsWritten(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new OptionRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertTrue($rule->present(new Node('opt_as', 1, [new Token(0, 'AS', 'AS', 0)])));
        self::assertFalse($rule->present(new Node('opt_as', 0, [])));
        self::assertTrue($rule->present(new Node('opt_if_not_exists', 1, [new Token(0, 'IF', 'IF', 0), new Node('not', 0, [new Token(0, 'NOT_SYM', 'NOT', 0)]), new Token(0, 'EXISTS', 'EXISTS', 0)])));
        self::assertTrue($rule->present(new Node('opt_wild', 1, [new Token(0, '.', '.', 0), new Token(0, '*', '*', 0)])));
        self::assertFalse($rule->present(new Node('opt_ignore', 0, [])));
    }

    public function testPresentReportsAProductionWithoutARule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new OptionRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: opt_restrict: RESTRICT');

        $rule->present(new Node('opt_restrict', 1, [new Token(0, 'RESTRICT', 'RESTRICT', 0)]));
    }

    public function testSkipAcceptsAnInertProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new OptionRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $rule->skip(new Node('key_or_index', 0, [new Token(0, 'KEY_SYM', 'KEY', 0)]));
        $rule->skip(new Node('not', 0, [new Token(0, 'NOT_SYM', 'NOT', 0)]));

        $this->expectExceptionMessage('No semantic rule is implemented for: opt_temporary: TEMPORARY');

        $rule->skip(new Node('opt_temporary', 1, [new Token(0, 'TEMPORARY', 'TEMPORARY', 0)]));
    }

    public function testDropBehaviorLowersRestrictAndCascade(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new OptionRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertNull($rule->dropBehavior(new Node('opt_restrict', 0, [])));
        self::assertSame(DropBehavior::Restrict, $rule->dropBehavior(new Node('opt_restrict', 1, [new Token(0, 'RESTRICT', 'RESTRICT', 0)])));
        self::assertSame(DropBehavior::Cascade, $rule->dropBehavior(new Node('opt_restrict', 2, [new Token(0, 'CASCADE', 'CASCADE', 0)])));
    }
}
