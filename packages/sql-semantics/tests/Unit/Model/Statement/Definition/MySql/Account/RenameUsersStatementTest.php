<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\AccountRename;
use SqlSemantics\Model\Statement\Definition\MySql\Account\RenameUsersStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameUsersStatement::class)]
#[Medium]
final class RenameUsersStatementTest extends TestCase
{
    public function testWithRenamesReplacesThePairsWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('RENAME USER a TO b');
        self::assertInstanceOf(RenameUsersStatement::class, $statement);
        $changed = $statement->withRenames([new AccountRename(CurrentAccount::Authenticated, new AccountName('c', '%'))]);
        self::assertCount(1, $statement->renames);
        self::assertSame("RENAME USER CURRENT_USER TO 'c'@'%'", $changed->toString());
        self::assertSame(StatementKind::Rename, $changed->kind);
    }

    public function testWithOriginRetainsThePairs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("RENAME USER a TO 'b'@'h', c TO d");
        self::assertInstanceOf(RenameUsersStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->renames, $copy->renames);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('RENAME USER a TO b');
        self::assertInstanceOf(RenameUsersStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }
}
