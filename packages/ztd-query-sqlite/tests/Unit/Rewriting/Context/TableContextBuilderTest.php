<?php

declare(strict_types=1);

namespace Tests\Unit\Rewriting\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use ZtdQuery\Platform\Sqlite\Rewriting\Context\TableContextBuilder;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinition;
use ZtdQuery\Schema\ViewDefinitionSet;

#[CoversClass(TableContextBuilder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Relation\RelationSourceParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteLexerProfile::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteViewShadowRenderer::class)]
final class TableContextBuilderTest extends TestCase
{
    public function testBuildTableContextCombinesFixturesDefinitionsAndViews(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], [], []);
        $registry->register('known', $definition);
        $registry->register('empty', $definition);
        $registry->register('removed', $definition);
        $registry->markRemoved('removed');
        $views = new ViewDefinitionSet();
        $views->register('visible', new ViewDefinition('SELECT * FROM known', ['known']));
        $views->register('known', new ViewDefinition('SELECT 99', []));
        $context = (new TableContextBuilder($registry, ['known' => [['id' => 1]], 'inferred' => [['a' => 'x'], ['b' => 'y']]], $views))->buildTableContext();
        self::assertSame([['id' => 1]], $context['known']['rows'] ?? null);
        self::assertSame(['a', 'b'], $context['inferred']['columns'] ?? null);
        self::assertSame([], $context['empty']['rows'] ?? null);
        self::assertSame([], $context['removed']['rows'] ?? null);
        self::assertSame('SELECT * FROM known', $context['visible']['viewSql'] ?? null);
    }

    public function testContextFromDefinitionPreservesValuesWithoutCoercion(): void
    {
        $object = new stdClass();
        $definition = new TableDefinition(['value'], ['value' => 'TEXT'], [], [], [], columnDefaults: ['value' => "'x'"]);
        $context = TableContextBuilder::contextFromDefinition($definition, [['value' => $object]]);
        self::assertSame($object, $context['rows'][0]['value']);
        self::assertSame(['value' => "'x'"], $context['columnDefaults']);
        self::assertSame(['value'], $context['columns']);
    }

    public function testColumnsFromRowsPreservesFirstSeenOrder(): void
    {
        self::assertSame(['b', 'a', 'c'], TableContextBuilder::columnsFromRows([['b' => 1, 'a' => 2], ['c' => 3, 'a' => 4]]));
        self::assertSame([], TableContextBuilder::columnsFromRows([]));
    }

}
