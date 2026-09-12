<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Rewrite\Context\TableContext;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlCteShadowComposer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlSelectRelationParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlViewShadowRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Cte\HeaderParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Cte\IdentifierReferences::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Relation\ReferenceReader::class)]
#[CoversClass(TableContext::class)]
final class TableContextTest extends TestCase
{
    public function testBuildTableContext(): void
    {
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $context = new TableContext(new \ZtdQuery\Platform\MySql\MySqlCteShadowComposer(), $registry, $store, new \ZtdQuery\Schema\ViewDefinitionSet());
        $store->set('users', [['id' => 1], ['name' => 'a']]);
        $registry->register('empty', new \ZtdQuery\Schema\TableDefinition(['code'], [], [], [], []));
        $tables = $context->buildTableContext();
        self::assertArrayHasKey('columns', $tables['users']);
        self::assertArrayHasKey('columns', $tables['empty']);
        self::assertSame(['id', 'name'], $tables['users']['columns'] ?? null);
        self::assertSame([['id' => 1], ['name' => 'a']], $tables['users']['rows']);
        self::assertSame(['code'], $tables['empty']['columns'] ?? null);
        self::assertSame([], $tables['empty']['rows']);
    }

    public function testFindUnknownTable(): void
    {
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $context = new TableContext(new \ZtdQuery\Platform\MySql\MySqlCteShadowComposer(), $registry, $store, new \ZtdQuery\Schema\ViewDefinitionSet());
        $store->set('users', [['id' => 1]]);
        self::assertNull($context->findUnknownTable('WITH c AS (SELECT * FROM users) SELECT * FROM c'));
        self::assertSame('missing', $context->findUnknownTable('SELECT * FROM users JOIN missing ON TRUE'));
    }

    public function testTableExists(): void
    {
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $context = new TableContext(new \ZtdQuery\Platform\MySql\MySqlCteShadowComposer(), $registry, $store, new \ZtdQuery\Schema\ViewDefinitionSet());
        self::assertFalse($context->tableExists('users'));
        $store->set('users', []);
        self::assertTrue($context->tableExists('users'));
        $registry->register('empty', new \ZtdQuery\Schema\TableDefinition(['code'], [], [], [], []));
        self::assertTrue($context->tableExists('empty'));
    }

    public function testHasSchemaContext(): void
    {
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $context = new TableContext(new \ZtdQuery\Platform\MySql\MySqlCteShadowComposer(), $registry, $store, new \ZtdQuery\Schema\ViewDefinitionSet());
        self::assertFalse($context->hasSchemaContext());
        $store->set('users', []);
        self::assertTrue($context->hasSchemaContext());
    }

    public function testRequireKnownTables(): void
    {
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $context = new TableContext(new \ZtdQuery\Platform\MySql\MySqlCteShadowComposer(), $registry, $store, new \ZtdQuery\Schema\ViewDefinitionSet());
        $store->set('users', []);
        $this->expectException(\ZtdQuery\Exception\UnknownSchemaException::class);
        $this->expectExceptionMessage('missing');
        $context->requireKnownTables('SELECT * FROM missing');
    }

}
