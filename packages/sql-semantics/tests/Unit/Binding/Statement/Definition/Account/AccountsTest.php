<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\Statement\Definition\Account\Accounts;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InputViolation;

#[CoversClass(Accounts::class)]
#[Medium]
final class AccountsTest extends TestCase
{
    public function testAccountKeepsTheAuthenticatedKeywordSymbolic(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("DROP USER CURRENT_USER(), 'CURRENT_USER'@'h'");
        self::assertSame(CurrentAccount::Authenticated, Accounts::account($tree->find('user')[0], new Identifiers(Dialect::MySql)));
        self::assertEquals(new AccountName('CURRENT_USER', 'h'), Accounts::account($tree->find('user')[1], new Identifiers(Dialect::MySql)));
    }

    public function testTargetReadsTheConnectingClientAccount(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER USER USER() DISCARD OLD PASSWORD');
        self::assertSame(ClientAccount::Connected, Accounts::target($tree->find('user_func')[0], new Identifiers(Dialect::MySql)));
    }

    public function testListKeepsRequestOrder(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('GRANT r TO b, a@h, CURRENT_USER');
        $accounts = Accounts::list($tree->find('user_list')[0], new Identifiers(Dialect::MySql));
        self::assertEquals([new AccountName('b'), new AccountName('a', 'h'), CurrentAccount::Authenticated], $accounts);
    }

    public function testLiteralKeepsTheCredentialSpelling(): void
    {
        $literal = Accounts::literal(new Token(0, 'TEXT_STRING', "'p''w'", 0));
        self::assertSame("'p''w'", $literal->text);
        self::assertSame(LiteralKind::Text, $literal->literalKind);
    }

    #[TestWith(['0007', 7])]
    #[TestWith(['0x1F', 31])]
    #[TestWith(["X'0A'", 10])]
    #[TestWith(['0', 0])]
    public function testCountReadsDecimalAndHexadecimalSpellings(string $text, int $expected): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('SELECT 1');
        self::assertSame($expected, Accounts::count(new Token(0, 'NUM', $text, 0), $tree));
    }

    #[TestWith(['1.5'])]
    #[TestWith(['99999999999999999999999'])]
    public function testCountRejectsFractionalOrOverflowingSpellings(string $text): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('SELECT 1');
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AccountLimit->message());
        Accounts::count(new Token(0, 'NUM', $text, 0), $tree);
    }
}
