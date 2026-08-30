<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Mutation\MySqlTableRedeclaration;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\Key\ForeignKeyDefinition;
use ZtdQuery\Schema\Key\ReferentialAction;
use ZtdQuery\Schema\TableDefinition;

#[CoversClass(MySqlTableRedeclaration::class)]
final class MySqlTableRedeclarationTest extends TestCase
{
    public function testSqlForDeclaresEveryColumnTheTableHolds(): void
    {
        $definition = new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(100)'], [], [], []);

        self::assertSame(
            'CREATE TABLE `users` (`id` INT, `name` VARCHAR(100))',
            (new MySqlTableRedeclaration())->sqlFor('users', $definition),
        );
    }

    public function testSqlForWritesAKeyOfSeveralColumnsOnItsOwn(): void
    {
        $definition = new TableDefinition(['a', 'b'], ['a' => 'INT', 'b' => 'INT'], ['a', 'b'], [], []);

        self::assertStringContainsString(
            'PRIMARY KEY (`a`, `b`)',
            (new MySqlTableRedeclaration())->sqlFor('t', $definition),
        );
    }

    public function testSqlForWritesEveryUniqueKeyUnderTheNameItHas(): void
    {
        $definition = new TableDefinition(['email'], ['email' => 'VARCHAR(255)'], [], [], ['by_email' => ['email']]);

        self::assertStringContainsString(
            'UNIQUE KEY `by_email` (`email`)',
            (new MySqlTableRedeclaration())->sqlFor('users', $definition),
        );
    }

    public function testSqlForWritesEveryForeignKeyWithWhatItDoesOnChange(): void
    {
        $definition = new TableDefinition(
            ['user_id'],
            ['user_id' => 'INT'],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            ['fk_user' => new ForeignKeyDefinition(
                ['user_id'],
                'users',
                ['id'],
                ReferentialAction::Cascade,
                ReferentialAction::Restrict,
            )],
        );

        self::assertStringContainsString(
            'CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT',
            (new MySqlTableRedeclaration())->sqlFor('posts', $definition),
        );
    }

    public function testColumnSqlDeclaresAColumnByTheTypeTheDialectWrote(): void
    {
        $definition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            [],
            [],
            [],
            ['id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'BIGINT UNSIGNED')],
        );

        self::assertSame('`id` BIGINT UNSIGNED', (new MySqlTableRedeclaration())->columnSql('id', $definition));
    }

    public function testColumnSqlFallsBackToTextForAColumnNothingTyped(): void
    {
        $definition = new TableDefinition(['note'], [], [], [], []);

        self::assertSame('`note` TEXT', (new MySqlTableRedeclaration())->columnSql('note', $definition));
    }

    public function testColumnSqlSaysWhenAColumnWillNotTakeNull(): void
    {
        $definition = new TableDefinition(['id'], ['id' => 'INT'], [], ['id'], []);

        self::assertSame('`id` INT NOT NULL', (new MySqlTableRedeclaration())->columnSql('id', $definition));
    }

    public function testColumnSqlWritesAKeyOfOneColumnOnTheColumn(): void
    {
        $definition = new TableDefinition(['id'], ['id' => 'INT'], ['id'], [], []);

        self::assertSame('`id` INT PRIMARY KEY', (new MySqlTableRedeclaration())->columnSql('id', $definition));
    }

    public function testQuotedListWritesEveryNameAsMySqlWouldNameIt(): void
    {
        self::assertSame('`a`, `b`', (new MySqlTableRedeclaration())->quotedList(['a', 'b']));
    }

    public function testQuotedListIsEmptyForNoNamesAtAll(): void
    {
        self::assertSame('', (new MySqlTableRedeclaration())->quotedList([]));
    }
}
