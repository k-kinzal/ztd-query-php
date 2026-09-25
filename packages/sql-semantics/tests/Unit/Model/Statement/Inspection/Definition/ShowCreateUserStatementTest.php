<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateUserStatement;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowDatabasesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowCreateUserStatement::class)]
#[Medium]
final class ShowCreateUserStatementTest extends TestCase
{
    public function testResultColumnsLabelTheDefinitionWithTheAccount(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW CREATE USER 'app'@'localhost'");
        self::assertInstanceOf(ShowCreateUserStatement::class, $statement);
        self::assertInstanceOf(AccountName::class, $statement->account);
        self::assertSame(['app', 'localhost'], [$statement->account->username, $statement->account->host]);
        self::assertSame(['CREATE USER for app@localhost'], array_column($statement->resultColumns(), 'name'));
        self::assertSame("SHOW CREATE USER 'app'@'localhost'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testResultColumnsReadAnOmittedHostAsThePercentHost(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE USER app');
        self::assertInstanceOf(ShowCreateUserStatement::class, $statement);
        self::assertSame('CREATE USER for app@%', $statement->resultColumns()[0]->name);
        self::assertSame('varchar', $statement->resultColumns()[0]->expression->type->name);
    }

    public function testWithAccountDescribesAnotherAccountImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE USER CURRENT_USER()');
        self::assertInstanceOf(ShowCreateUserStatement::class, $statement);
        self::assertSame(CurrentAccount::Authenticated, $statement->account);
        self::assertSame('CREATE USER for CURRENT_USER', $statement->resultColumns()[0]->name);
        $changed = $statement->withAccount(new AccountName('reader', 'localhost'));
        self::assertNotSame($statement, $changed);
        self::assertSame(CurrentAccount::Authenticated, $statement->account);
        self::assertSame("SHOW CREATE USER 'reader'@'localhost'", (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('SHOW CREATE USER CURRENT_USER', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginRetainsTheAccount(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW CREATE USER 'app'@'localhost'");
        self::assertInstanceOf(ShowCreateUserStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->account, $copy->account);
    }

    public function testRejectsTheRelease56Grammar(): void
    {
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SHOW DATABASES');
        self::assertInstanceOf(ShowDatabasesStatement::class, $legacy);
        $this->expectException(InvalidStructure::class);
        new ShowCreateUserStatement($legacy->origin, CurrentAccount::Authenticated);
    }
}
