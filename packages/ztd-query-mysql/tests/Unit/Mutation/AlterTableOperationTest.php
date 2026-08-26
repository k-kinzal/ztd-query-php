<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\MySqlAlterStatements;
use ZtdQuery\Exception\ColumnAlreadyExistsException;
use ZtdQuery\Exception\ColumnNotFoundException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Mutation\AlterTableColumn;
use ZtdQuery\Platform\MySql\Mutation\AlterTableOperation;
use ZtdQuery\Platform\MySql\Mutation\AlterTableRows;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(AlterTableOperation::class)]
#[UsesClass(AlterTableColumn::class)]
#[UsesClass(AlterTableRows::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(ColumnAlreadyExistsException::class)]
#[UsesClass(ColumnNotFoundException::class)]
#[UsesClass(UnsupportedSqlException::class)]
final class AlterTableOperationTest extends TestCase
{
    public function testApplyToAddsTheColumnAnAddDeclares(): void
    {
        $createStmt = MySqlAlterStatements::usersDeclaration();

        MySqlAlterStatements::operationsFor('ALTER TABLE users ADD COLUMN email VARCHAR(255)')
            ->applyTo($createStmt, MySqlAlterStatements::operation('ALTER TABLE users ADD COLUMN email VARCHAR(255)'), new ShadowStore(), MySqlAlterStatements::usersDefinition(), 'users');

        self::assertStringContainsString('email', $createStmt->build());
    }

    public function testApplyToAnswersTheNameTheTableHasAfterARename(): void
    {
        self::assertSame(
            'people',
            MySqlAlterStatements::operationsFor('ALTER TABLE users RENAME TO people')->applyTo(
                MySqlAlterStatements::usersDeclaration(),
                MySqlAlterStatements::operation('ALTER TABLE users RENAME TO people'),
                new ShadowStore(),
                MySqlAlterStatements::usersDefinition(),
                'users',
            ),
        );
    }

    public function testApplyToLeavesTheNameAloneForEveryOtherOperation(): void
    {
        self::assertSame(
            'users',
            MySqlAlterStatements::operationsFor('ALTER TABLE users DROP COLUMN name')->applyTo(
                MySqlAlterStatements::usersDeclaration(),
                MySqlAlterStatements::operation('ALTER TABLE users DROP COLUMN name'),
                new ShadowStore(),
                MySqlAlterStatements::usersDefinition(),
                'users',
            ),
        );
    }

    public function testApplyToLeavesAForeignKeyToTheDeclarationTheRegistryHolds(): void
    {
        $createStmt = MySqlAlterStatements::usersDeclaration();
        $before = $createStmt->build();

        MySqlAlterStatements::operationsFor('ALTER TABLE users DROP FOREIGN KEY fk')->applyTo(
            $createStmt,
            MySqlAlterStatements::operation('ALTER TABLE users DROP FOREIGN KEY fk'),
            new ShadowStore(),
            MySqlAlterStatements::usersDefinition(),
            'users',
        );

        self::assertSame($before, $createStmt->build());
    }

    public function testApplyToRefusesAnOperationZtdCannotSimulate(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        MySqlAlterStatements::operationsFor('ALTER TABLE users ADD SPATIAL INDEX idx (g)')->applyTo(
            MySqlAlterStatements::usersDeclaration(),
            MySqlAlterStatements::operation('ALTER TABLE users ADD SPATIAL INDEX idx (g)'),
            new ShadowStore(),
            MySqlAlterStatements::usersDefinition(),
            'users',
        );
    }

    public function testAddColumnRefusesAColumnTheTableAlreadyHas(): void
    {
        $this->expectException(ColumnAlreadyExistsException::class);

        MySqlAlterStatements::operationsFor('ALTER TABLE users ADD COLUMN name VARCHAR(10)')->addColumn(
            MySqlAlterStatements::usersDeclaration(),
            MySqlAlterStatements::operation('ALTER TABLE users ADD COLUMN name VARCHAR(10)'),
            MySqlAlterStatements::usersDefinition(),
            'users',
        );
    }

    public function testDropColumnTakesItOffTheDeclarationAndTheRows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'a']]);
        $createStmt = MySqlAlterStatements::usersDeclaration();

        MySqlAlterStatements::operationsFor('ALTER TABLE users DROP COLUMN name')->dropColumn(
            $createStmt,
            MySqlAlterStatements::operation('ALTER TABLE users DROP COLUMN name'),
            $store,
            MySqlAlterStatements::usersDefinition(),
            'users',
        );

        self::assertSame([['id' => 1]], $store->get('users'));
    }

    public function testDropColumnRefusesAColumnTheTableDoesNotHave(): void
    {
        $this->expectException(ColumnNotFoundException::class);

        MySqlAlterStatements::operationsFor('ALTER TABLE users DROP COLUMN missing')->dropColumn(
            MySqlAlterStatements::usersDeclaration(),
            MySqlAlterStatements::operation('ALTER TABLE users DROP COLUMN missing'),
            new ShadowStore(),
            MySqlAlterStatements::usersDefinition(),
            'users',
        );
    }

    public function testModifyColumnWritesTheNewDeclarationOverTheOldOne(): void
    {
        $createStmt = MySqlAlterStatements::usersDeclaration();

        MySqlAlterStatements::operationsFor('ALTER TABLE users MODIFY COLUMN name TEXT')->modifyColumn(
            $createStmt,
            MySqlAlterStatements::operation('ALTER TABLE users MODIFY COLUMN name TEXT'),
            MySqlAlterStatements::usersDefinition(),
            'users',
        );

        self::assertStringContainsString('text', strtolower($createStmt->build()));
    }

    public function testModifyColumnRefusesAColumnTheTableDoesNotHave(): void
    {
        $this->expectException(ColumnNotFoundException::class);

        MySqlAlterStatements::operationsFor('ALTER TABLE users MODIFY COLUMN missing TEXT')->modifyColumn(
            MySqlAlterStatements::usersDeclaration(),
            MySqlAlterStatements::operation('ALTER TABLE users MODIFY COLUMN missing TEXT'),
            MySqlAlterStatements::usersDefinition(),
            'users',
        );
    }

    public function testChangeColumnCarriesTheValuesOverToTheNewName(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'a']]);

        MySqlAlterStatements::operationsFor('ALTER TABLE users CHANGE name full_name VARCHAR(200)')->changeColumn(
            MySqlAlterStatements::usersDeclaration(),
            MySqlAlterStatements::operation('ALTER TABLE users CHANGE name full_name VARCHAR(200)'),
            $store,
            MySqlAlterStatements::usersDefinition(),
            'users',
        );

        self::assertSame([['id' => 1, 'full_name' => 'a']], $store->get('users'));
    }

    public function testChangeColumnRefusesAColumnTheTableDoesNotHave(): void
    {
        $this->expectException(ColumnNotFoundException::class);

        MySqlAlterStatements::operationsFor('ALTER TABLE users CHANGE missing other VARCHAR(1)')->changeColumn(
            MySqlAlterStatements::usersDeclaration(),
            MySqlAlterStatements::operation('ALTER TABLE users CHANGE missing other VARCHAR(1)'),
            new ShadowStore(),
            MySqlAlterStatements::usersDefinition(),
            'users',
        );
    }

    public function testRenameColumnLeavesHowTheColumnIsDeclaredAlone(): void
    {
        $createStmt = MySqlAlterStatements::usersDeclaration();

        MySqlAlterStatements::operationsFor('ALTER TABLE users RENAME COLUMN name TO full_name')->renameColumn(
            $createStmt,
            MySqlAlterStatements::operation('ALTER TABLE users RENAME COLUMN name TO full_name'),
            new ShadowStore(),
            MySqlAlterStatements::usersDefinition(),
            'users',
        );

        self::assertStringContainsString('`full_name` varchar(100)', $createStmt->build());
    }

    public function testRenameColumnRefusesAColumnTheTableDoesNotHave(): void
    {
        $this->expectException(ColumnNotFoundException::class);

        MySqlAlterStatements::operationsFor('ALTER TABLE users RENAME COLUMN missing TO other')->renameColumn(
            MySqlAlterStatements::usersDeclaration(),
            MySqlAlterStatements::operation('ALTER TABLE users RENAME COLUMN missing TO other'),
            new ShadowStore(),
            MySqlAlterStatements::usersDefinition(),
            'users',
        );
    }

    public function testRenameTableLeavesTheOldNameHoldingNothing(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);

        MySqlAlterStatements::operationsFor('ALTER TABLE users RENAME TO people')->renameTable(
            MySqlAlterStatements::operation('ALTER TABLE users RENAME TO people'),
            $store,
            'users',
        );

        self::assertSame([[], []], [$store->get('users'), []]);
    }

    public function testRenameTableMovesTheRowsToTheNewName(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);

        MySqlAlterStatements::operationsFor('ALTER TABLE users RENAME TO people')->renameTable(
            MySqlAlterStatements::operation('ALTER TABLE users RENAME TO people'),
            $store,
            'users',
        );

        self::assertSame([['id' => 1]], $store->get('people'));
    }

    public function testRenameTableLeavesTheNameAloneWhereTheOperationNamesNone(): void
    {
        self::assertSame(
            'users',
            MySqlAlterStatements::operationsFor('ALTER TABLE users DROP COLUMN name')->renameTable(
                MySqlAlterStatements::operation('ALTER TABLE users DROP COLUMN name'),
                new ShadowStore(),
                'users',
            ),
        );
    }

    public function testAddPrimaryKeyDeclaresAKeyOverTheColumnsItNames(): void
    {
        $createStmt = MySqlAlterStatements::usersDeclaration();

        MySqlAlterStatements::operationsFor('ALTER TABLE users ADD PRIMARY KEY (`id`)')->addPrimaryKey(
            $createStmt,
            MySqlAlterStatements::operation('ALTER TABLE users ADD PRIMARY KEY (`id`)'),
        );

        self::assertStringContainsString('PRIMARY KEY', $createStmt->build());
    }

    public function testDropPrimaryKeyTakesTheKeyOffTheColumnItWasWrittenOn(): void
    {
        $createStmt = MySqlAlterStatements::declaration('CREATE TABLE `users` (`id` INT PRIMARY KEY, `name` VARCHAR(100))');

        MySqlAlterStatements::operationsFor('ALTER TABLE users DROP PRIMARY KEY')->dropPrimaryKey($createStmt);

        self::assertStringNotContainsString('PRIMARY KEY', $createStmt->build());
    }

    public function testDropPrimaryKeyTakesOffAKeyWrittenOnItsOwn(): void
    {
        $createStmt = MySqlAlterStatements::declaration('CREATE TABLE `t` (`a` INT, `b` INT, PRIMARY KEY (`a`, `b`))');

        MySqlAlterStatements::operationsFor('ALTER TABLE t DROP PRIMARY KEY')->dropPrimaryKey($createStmt);

        self::assertStringNotContainsString('PRIMARY KEY', $createStmt->build());
    }

    public function testRenamedToAnswersTheNameTheOperationRenamesTo(): void
    {
        self::assertSame(
            'people',
            MySqlAlterStatements::operationsFor('ALTER TABLE users RENAME TO people')
                ->renamedTo(MySqlAlterStatements::operation('ALTER TABLE users RENAME TO people')),
        );
    }

    public function testRenamedToIsNothingWhereTheOperationRenamesNothing(): void
    {
        self::assertNull(
            MySqlAlterStatements::operationsFor('ALTER TABLE users DROP COLUMN name')
                ->renamedTo(MySqlAlterStatements::operation('ALTER TABLE users DROP COLUMN name')),
        );
    }

    public function testAssertDeclaredRefusesAColumnTheTableDoesNotHave(): void
    {
        $this->expectException(ColumnNotFoundException::class);

        MySqlAlterStatements::operationsFor('ALTER TABLE users DROP COLUMN missing')
            ->assertDeclared('missing', MySqlAlterStatements::usersDefinition(), 'users');
    }

    public function testAssertDeclaredAcceptsAColumnTheTableHas(): void
    {
        $operations = MySqlAlterStatements::operationsFor('ALTER TABLE users DROP COLUMN name');
        $definition = MySqlAlterStatements::usersDefinition();

        $operations->assertDeclared('name', $definition, 'users');

        self::assertContains('name', $definition->columns);
    }

    public function testReplaceFieldWritesTheNewDeclarationInPlaceOfTheOld(): void
    {
        $createStmt = MySqlAlterStatements::usersDeclaration();
        $columns = new AlterTableColumn();
        $replacement = $columns->firstFieldOf('CREATE TABLE t (`name` TEXT)');
        self::assertNotNull($replacement);

        MySqlAlterStatements::operationsFor('ALTER TABLE users MODIFY COLUMN name TEXT')
            ->replaceField($createStmt, 'name', $replacement);

        self::assertStringContainsString('text', strtolower($createStmt->build()));
    }

    public function testReplaceFieldLeavesADeclarationWithNoSuchColumnAlone(): void
    {
        $createStmt = MySqlAlterStatements::usersDeclaration();
        $before = $createStmt->build();
        $columns = new AlterTableColumn();
        $replacement = $columns->firstFieldOf('CREATE TABLE t (`other` TEXT)');
        self::assertNotNull($replacement);

        MySqlAlterStatements::operationsFor('ALTER TABLE users MODIFY COLUMN other TEXT')
            ->replaceField($createStmt, 'missing', $replacement);

        self::assertSame($before, $createStmt->build());
    }

    public function testStatementSqlAnswersTheStatementAsItWasWritten(): void
    {
        self::assertStringContainsString(
            'ALTER TABLE',
            MySqlAlterStatements::operationsFor('ALTER TABLE users DROP COLUMN name')->statementSql(),
        );
    }
}
