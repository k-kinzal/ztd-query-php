<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Session\Passwords;

#[CoversClass(Passwords::class)]
#[Medium]
final class PasswordsTest extends TestCase
{
    public function testWriteQuotesTheAccountNameAsOneIdentifier(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SET PASSWORD = 'new'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Password\SetPasswordStatement::class, $statement);
        $changed = $statement->withAccount(new \SqlSemantics\Model\Configuration\Account\AccountName("x'; DROP TABLE t; --", 'local@host'));
        $rebound = $binder->bind($changed->toString());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Password\SetPasswordStatement::class, $rebound);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Account\AccountName::class, $rebound->account);
        self::assertSame("x'; DROP TABLE t; --", $rebound->account->username);
        self::assertSame('local@host', $rebound->account->host);
    }

    public function testClauseKeepsCredentialSourcesAndVerificationSeparate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET PASSWORD FOR 'u' TO RANDOM REPLACE 'old' RETAIN CURRENT PASSWORD");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Password\SetRandomPasswordStatement::class, $statement);
        self::assertSame("PASSWORD FOR 'u' TO RANDOM REPLACE 'old' RETAIN CURRENT PASSWORD", Passwords::clause($statement)->toString());
    }
}
