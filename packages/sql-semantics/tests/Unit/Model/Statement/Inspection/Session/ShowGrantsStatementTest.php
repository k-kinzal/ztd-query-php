<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Statement\Inspection\Session\ShowGrantsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowGrantsStatement::class)]
#[Medium]
final class ShowGrantsStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testResultColumnsCarryTheDescribedAccountAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("SHOW GRANTS FOR 'app'@'localhost'");
        self::assertInstanceOf(ShowGrantsStatement::class, $statement);
        self::assertInstanceOf(AccountName::class, $statement->account);
        self::assertSame(['app', 'localhost'], [$statement->account->username, $statement->account->host]);
        self::assertSame([], $statement->roles);
        self::assertSame(['Grants for app@localhost'], array_column($statement->resultColumns(), 'name'));
        self::assertSame("SHOW GRANTS FOR 'app'@'localhost'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(['SHOW GRANTS'])]
    #[TestWith(['SHOW GRANTS FOR CURRENT_USER'])]
    #[TestWith(['SHOW GRANTS FOR CURRENT_USER()'])]
    public function testTheAuthenticatedAccountIsWrittenWithoutFor(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertInstanceOf(ShowGrantsStatement::class, $statement);
        self::assertSame(CurrentAccount::Authenticated, $statement->account);
        self::assertSame(['Grants for CURRENT_USER'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW GRANTS', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithAccountDescribesAnotherAccountImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW GRANTS');
        self::assertInstanceOf(ShowGrantsStatement::class, $statement);
        $changed = $statement->withAccount(new AccountName('app'));
        self::assertNotSame($statement, $changed);
        self::assertSame(CurrentAccount::Authenticated, $statement->account);
        self::assertSame("SHOW GRANTS FOR 'app'", (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('Grants for app@%', $changed->resultColumns()[0]->name);
    }

    public function testWithRolesActivatesRolesImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW GRANTS');
        self::assertInstanceOf(ShowGrantsStatement::class, $statement);
        $changed = $statement->withRoles([new AccountName('reader', 'h'), CurrentAccount::Authenticated]);
        self::assertNotSame($statement, $changed);
        self::assertSame([], $statement->roles);
        self::assertSame("SHOW GRANTS FOR CURRENT_USER USING 'reader'@'h', CURRENT_USER", (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('SHOW GRANTS', (new \SqlSemantics\SimpleSerializer())->serialize($changed->withRoles([])));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW GRANTS FOR a USING b');
        self::assertInstanceOf(ShowGrantsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->account, $statement->roles], [$copy->account, $copy->roles]);
    }

    public function testRejectsRolesBeforeMySql80(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SHOW GRANTS');
        $this->expectException(InvalidStructure::class);
        new ShowGrantsStatement($statement->origin, CurrentAccount::Authenticated, [new AccountName('reader')]);
    }
}
