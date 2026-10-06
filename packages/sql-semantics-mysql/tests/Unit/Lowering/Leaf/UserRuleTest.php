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
use SqlSemantics\Platform\MySql\Lowering\Leaf\UserRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;

#[CoversClass(UserRule::class)]
#[Medium]
final class UserRuleTest extends TestCase
{
    public function testAccountLowersEveryAccountForm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new UserRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $text = static fn (string $name): Node => new Node('ident_or_text', 1, [new Node('TEXT_STRING_sys', 0, [new Token(0, 'TEXT_STRING', "'" . $name . "'", 0)])]);
        $host = $rule->account(new Node('user', 0, [new Node('user_ident_or_text', 1, [$text('admin'), new Token(0, '@', '@', 0), $text('localhost')])]));
        $bare = $rule->account(new Node('user', 0, [new Node('user_ident_or_text', 0, [$text('app')])]));
        $current = $rule->account(new Node('user', 1, [new Token(0, 'CURRENT_USER', 'CURRENT_USER', 0), new Node('optional_braces', 1, [new Token(0, '(', '(', 0), new Token(0, ')', ')', 0)])]));
        $role = $rule->account(new Node('role', 0, [new Node('role_ident_or_text', 0, [new Node('role_ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', 'r1', 0)])])])]));

        self::assertInstanceOf(AccountName::class, $host);
        self::assertSame('admin', $host->user->value);
        self::assertSame('localhost', $host->host?->value);
        self::assertInstanceOf(AccountName::class, $bare);
        self::assertNull($bare->host);
        self::assertInstanceOf(CurrentUser::class, $current);
        self::assertInstanceOf(AccountName::class, $role);
        self::assertSame('r1', $role->user->value);
    }

    public function testAccountsFlattensAUserList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new UserRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $user = static fn (string $name): Node => new Node('user', 0, [new Node('user_ident_or_text', 0, [new Node('ident_or_text', 0, [new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', $name, 0)])])])])]);
        $accounts = $rule->accounts(new Node('user_list', 1, [new Node('user_list', 0, [$user('a')]), new Token(0, ',', ',', 0), $user('b')]));

        self::assertCount(2, $accounts);
        self::assertInstanceOf(AccountName::class, $accounts[1]);
        self::assertSame('b', $accounts[1]->user->value);
    }

    public function testDefinerLowersTheDefinerClauseOrNothing(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new UserRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $user = new Node('user', 0, [new Node('user_ident_or_text', 0, [new Node('ident_or_text', 0, [new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', 'owner', 0)])])])])]);
        $definer = $rule->definer(new Node('definer_opt', 1, [new Node('definer', 0, [new Token(0, 'DEFINER_SYM', 'DEFINER', 0), new Token(0, 'EQ', '=', 0), $user])]));

        self::assertInstanceOf(AccountName::class, $definer);
        self::assertSame('owner', $definer->user->value);
        self::assertNull($rule->definer(new Node('definer_opt', 0, [new Node('no_definer', 0, [])])));
    }
}
