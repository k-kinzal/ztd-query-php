<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Dialect\MySqlColumnTypeMapper;
use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\Dialect\MySqlStatementOptions;
use ZtdQuery\Platform\MySql\Dialect\MySqlValueRenderer;
use ZtdQuery\Platform\MySql\Parse\MySqlForeignKeyDefinitionParser;
use ZtdQuery\Platform\MySql\Parse\MySqlParser;
use ZtdQuery\Platform\MySql\Parse\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\Rewrite\MySqlCteShadowComposer;
use ZtdQuery\Platform\MySql\Rewrite\MySqlGeneratedColumnProjector;
use ZtdQuery\Platform\MySql\Rewrite\MySqlShadowTables;
use ZtdQuery\Platform\MySql\Rewrite\MySqlViewShadowRenderer;
use ZtdQuery\Platform\MySql\Transformer\MySqlUpdateClauses;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(MySqlShadowTables::class)]
#[UsesClass(MySqlViewShadowRenderer::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(MySqlColumnTypeMapper::class)]
#[UsesClass(MySqlLexerProfile::class)]
#[UsesClass(MySqlStatementOptions::class)]
#[UsesClass(MySqlValueRenderer::class)]
#[UsesClass(MySqlForeignKeyDefinitionParser::class)]
#[UsesClass(MySqlCteShadowComposer::class)]
#[UsesClass(MySqlGeneratedColumnProjector::class)]
#[UsesClass(MySqlUpdateClauses::class)]
final class MySqlShadowTablesTest extends TestCase
{
    public function testOfCarriesTheRowsTheShadowHolds(): void
    {
        $store = new ShadowStore();
        $store->set('t', [['id' => 1]]);
        $tables = new MySqlShadowTables($store, new TableDefinitionRegistry());

        $held = $tables->of(new ViewDefinitionSet());

        self::assertSame([['id' => 1]], $held['t']['rows'] ?? null);
    }

    public function testOfCarriesATableThatWasDeclaredButHoldsNoRows(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser(new MySqlParser()))->parse('CREATE TABLE t (id INT)');
        self::assertNotNull($definition);
        $registry->register('t', $definition);
        $tables = new MySqlShadowTables(new ShadowStore(), $registry);

        $held = $tables->of(new ViewDefinitionSet());

        self::assertSame([[], ['id']], [$held['t']['rows'] ?? null, $held['t']['columns'] ?? null]);
    }

    public function testHeldForReadsTheColumnsOffTheRowsWhereNothingDeclaredThem(): void
    {
        $tables = new MySqlShadowTables(new ShadowStore(), new TableDefinitionRegistry());

        $held = $tables->heldFor(null, [['id' => 1]]);

        self::assertSame([['id'], [], []], [$held['columns'], $held['primaryKeys'] ?? [], $held['candidateKeys'] ?? []]);
    }

    public function testHeldForTakesTheColumnsFromWhatDeclaredTheTable(): void
    {
        $definition = (new MySqlSchemaParser(new MySqlParser()))->parse('CREATE TABLE t (id INT PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $tables = new MySqlShadowTables(new ShadowStore(), new TableDefinitionRegistry());

        $held = $tables->heldFor($definition, []);

        self::assertSame([['id', 'name'], ['id']], [$held['columns'], $held['primaryKeys'] ?? []]);
    }

    public function testColumnsAcrossGoesOverEveryRowBecauseTheyNeedNotAgree(): void
    {
        $tables = new MySqlShadowTables(new ShadowStore(), new TableDefinitionRegistry());

        self::assertSame(['id', 'name'], $tables->columnsAcross([['id' => 1], ['id' => 2, 'name' => 'a']]));
    }

    public function testColumnsAcrossAnswersNothingWhereThereAreNoRows(): void
    {
        self::assertSame([], (new MySqlShadowTables(new ShadowStore(), new TableDefinitionRegistry()))->columnsAcross([]));
    }
}
