<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use RuntimeException;
use ZtdQuery\Platform\MySql\Transformer\CteGenerator;
use ZtdQuery\Rewrite\Shadowing\CteShadowing;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableAsSelectMutation;
use ZtdQuery\Shadow\ShadowStore;

final class CreateTableAsSelectMutationTest extends TestCase
{
    private function createCteShadowing(SchemaRegistry $registry): CteShadowing
    {
        return new CteShadowing(new CteGenerator(), $registry);
    }

    public function testApplyRegistersTableWithColumnsFromSelect(): void
    {
        $registry = new SchemaRegistry();
        $store = new ShadowStore();
        $shadowing = $this->createCteShadowing($registry);

        $parser = new Parser('SELECT id, name FROM users');
        /** @var SelectStatement $selectStmt */
        $selectStmt = $parser->statements[0];

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            $selectStmt,
            $registry,
            $store,
            $shadowing
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice']]);

        $schema = $registry->get('users_copy');
        $this->assertNotNull($schema);
        $this->assertStringContainsString('users_copy', $schema);
        $this->assertStringContainsString('id', $schema);
        $this->assertStringContainsString('name', $schema);
    }

    public function testApplyStoresRowsFromSelectResult(): void
    {
        $registry = new SchemaRegistry();
        $store = new ShadowStore();
        $shadowing = $this->createCteShadowing($registry);

        $parser = new Parser('SELECT id, name FROM users');
        /** @var SelectStatement $selectStmt */
        $selectStmt = $parser->statements[0];

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            $selectStmt,
            $registry,
            $store,
            $shadowing
        );
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
        $mutation->apply($store, $rows);

        $this->assertSame($rows, $store->get('users_copy'));
    }

    public function testTableNameReturnsNewTableName(): void
    {
        $registry = new SchemaRegistry();
        $store = new ShadowStore();
        $shadowing = $this->createCteShadowing($registry);

        $parser = new Parser('SELECT id FROM users');
        /** @var SelectStatement $selectStmt */
        $selectStmt = $parser->statements[0];

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            $selectStmt,
            $registry,
            $store,
            $shadowing
        );

        $this->assertSame('users_copy', $mutation->tableName());
    }

    public function testApplyThrowsExceptionWhenTableExists(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users_copy', 'CREATE TABLE users_copy (id INT)');
        $store = new ShadowStore();
        $shadowing = $this->createCteShadowing($registry);

        $parser = new Parser('SELECT id FROM users');
        /** @var SelectStatement $selectStmt */
        $selectStmt = $parser->statements[0];

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            $selectStmt,
            $registry,
            $store,
            $shadowing
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Table 'users_copy' already exists.");

        $mutation->apply($store, [['id' => 1]]);
    }

    public function testApplyWithIfNotExistsSkipsWhenTableExists(): void
    {
        $registry = new SchemaRegistry();
        $originalSql = 'CREATE TABLE users_copy (id INT)';
        $registry->register('users_copy', $originalSql);
        $store = new ShadowStore();
        $shadowing = $this->createCteShadowing($registry);

        $parser = new Parser('SELECT id, name FROM users');
        /** @var SelectStatement $selectStmt */
        $selectStmt = $parser->statements[0];

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            $selectStmt,
            $registry,
            $store,
            $shadowing,
            true
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice']]);

        // Original schema should be preserved
        $this->assertSame($originalSql, $registry->get('users_copy'));
    }

    public function testGetSelectSqlReturnsTransformedSelectQuery(): void
    {
        $registry = new SchemaRegistry();
        $store = new ShadowStore();
        $shadowing = $this->createCteShadowing($registry);

        $parser = new Parser('SELECT id, name FROM users');
        /** @var SelectStatement $selectStmt */
        $selectStmt = $parser->statements[0];

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            $selectStmt,
            $registry,
            $store,
            $shadowing
        );

        $selectSql = $mutation->getSelectSql();
        $this->assertStringContainsString('SELECT', $selectSql);
        $this->assertStringContainsString('users', $selectSql);
    }

    public function testApplyInfersColumnsFromResultRowsForSelectStar(): void
    {
        $registry = new SchemaRegistry();
        $store = new ShadowStore();
        $shadowing = $this->createCteShadowing($registry);

        $parser = new Parser('SELECT * FROM users');
        /** @var SelectStatement $selectStmt */
        $selectStmt = $parser->statements[0];

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            $selectStmt,
            $registry,
            $store,
            $shadowing
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);

        $schema = $registry->get('users_copy');
        $this->assertNotNull($schema);
        $this->assertStringContainsString('id', $schema);
        $this->assertStringContainsString('name', $schema);
        $this->assertStringContainsString('email', $schema);
    }

    public function testApplyThrowsExceptionWhenNoColumnsCanBeDetermined(): void
    {
        $registry = new SchemaRegistry();
        $store = new ShadowStore();
        $shadowing = $this->createCteShadowing($registry);

        $parser = new Parser('SELECT * FROM users');
        /** @var SelectStatement $selectStmt */
        $selectStmt = $parser->statements[0];

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            $selectStmt,
            $registry,
            $store,
            $shadowing
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot determine columns for CREATE TABLE AS SELECT.');

        $mutation->apply($store, []);
    }
}
