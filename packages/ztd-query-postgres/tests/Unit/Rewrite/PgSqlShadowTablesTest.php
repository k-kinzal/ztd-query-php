<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlIdentifierQuoter;
use ZtdQuery\Platform\Postgres\Parse\PgSqlSchemaParser;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlPartitionPredicateRenderer;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlShadowTables;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlViewShadowRenderer;
use ZtdQuery\Schema\Partition\TablePartitionRelation;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(PgSqlShadowTables::class)]
#[UsesClass(PgSqlPartitionPredicateRenderer::class)]
#[UsesClass(PgSqlIdentifierQuoter::class)]
#[UsesClass(PgSqlViewShadowRenderer::class)]
#[UsesClass(PgSqlSchemaParser::class)]
final class PgSqlShadowTablesTest extends TestCase
{
    public function testOfCarriesTheRowsTheShadowHolds(): void
    {
        $store = new ShadowStore();
        $store->set('t', [['id' => 1]]);

        $held = (new PgSqlShadowTables($store, new TableDefinitionRegistry()))->of(new ViewDefinitionSet());

        self::assertSame([['id' => 1]], $held['t']['rows'] ?? null);
    }

    public function testOfCarriesATableThatWasDeclaredButHoldsNoRows(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = (new PgSqlSchemaParser())->parse('CREATE TABLE t (id integer)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);

        $held = (new PgSqlShadowTables(new ShadowStore(), $registry))->of(new ViewDefinitionSet());

        self::assertSame([[], ['id']], [$held['t']['rows'] ?? null, $held['t']['columns'] ?? null]);
    }

    public function testHeldForReadsTheColumnsOffTheRowsWhereNothingDeclaredThem(): void
    {
        $tables = new PgSqlShadowTables(new ShadowStore(), new TableDefinitionRegistry());

        self::assertSame(['id'], $tables->heldFor(null, [['id' => 1]])['columns']);
    }

    public function testColumnsAcrossGoesOverEveryRowBecauseTheyNeedNotAgree(): void
    {
        $tables = new PgSqlShadowTables(new ShadowStore(), new TableDefinitionRegistry());

        self::assertSame(['id', 'name'], $tables->columnsAcross([['id' => 1], ['id' => 2, 'name' => 'a']]));
    }

    public function testWithPartitionsLeavesATableThatPartitionsNothing(): void
    {
        $tables = new PgSqlShadowTables(new ShadowStore(), new TableDefinitionRegistry());

        self::assertSame([], $tables->withPartitions([]));
    }

    public function testSiblingPredicatesAnswersWhatEveryPartitionOfATableHoldsOfIt(): void
    {
        $registry = new TableDefinitionRegistry();
        $parser = new PgSqlSchemaParser();
        $child = $parser->parse('CREATE TABLE logs_2024 (id integer)');
        self::assertNotNull($child);
        $registry->register(
            'logs_2024',
            $child->withPartitionRelation(new TablePartitionRelation('logs', 'id < 10')),
        );
        $tables = new PgSqlShadowTables(new ShadowStore(), $registry);

        self::assertSame(['id < 10'], $tables->siblingPredicates($registry->getAll(), 'logs'));
    }

    public function testStorageTableAnswersTheTableAPartitionsRowsAreHeldIn(): void
    {
        $registry = new TableDefinitionRegistry();
        $parser = new PgSqlSchemaParser();
        $child = $parser->parse('CREATE TABLE logs_2024 (id integer)');
        self::assertNotNull($child);
        $registry->register(
            'logs_2024',
            $child->withPartitionRelation(new TablePartitionRelation('logs', 'id < 10')),
        );
        $tables = new PgSqlShadowTables(new ShadowStore(), $registry);

        self::assertSame(['logs', 'other'], [$tables->storageTable('logs_2024'), $tables->storageTable('other')]);
    }
}
