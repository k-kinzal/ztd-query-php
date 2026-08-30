<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation\Row;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Connection\ResultSet;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\Table\CreateTableAsSelectMutation;
use ZtdQuery\Shadow\ShadowStore;

#[CoversNothing]
final class ResultSetMutationTest extends TestCase
{
    public function testApplyResultSetGivesTheNewTableTheColumnsTheResultDescribed(): void
    {
        $registry = new TableDefinitionRegistry();
        $mutation = new CreateTableAsSelectMutation(
            'copy',
            [],
            $registry,
            new ColumnDeclaration(ColumnTypeFamily::TEXT, 'fixture_text'),
        );

        $mutation->applyResultSet(new ShadowStore(), new ResultSet(
            [],
            [new ResultColumn('id', new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'BIGINT'))],
        ));

        self::assertSame(['id'], $registry->get('copy')?->columns);
    }

    public function testApplyResultSetGivesTheNewTableTheRowsTheResultCarried(): void
    {
        $store = new ShadowStore();
        $mutation = new CreateTableAsSelectMutation(
            'copy',
            ['id'],
            new TableDefinitionRegistry(),
            new ColumnDeclaration(ColumnTypeFamily::TEXT, 'fixture_text'),
        );

        $mutation->applyResultSet($store, new ResultSet([['id' => 1], ['id' => 2]], []));

        self::assertSame([['id' => 1], ['id' => 2]], $store->get('copy'));
    }
}
