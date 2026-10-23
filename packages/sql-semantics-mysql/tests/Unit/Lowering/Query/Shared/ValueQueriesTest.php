<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ValueQueries;

#[CoversClass(ValueQueries::class)]
#[Medium]
final class ValueQueriesTest extends TestCase
{
    public function testValuesStopsAtASetOperation(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions($semantics->profile()), new \SqlSemantics\Lowering\Leaves(), $semantics->profile());
        $tree = $semantics->parser()->parse('INSERT INTO t VALUES ROW(1) UNION VALUES ROW(2)');
        $source = $tree->find('query_expression_with_opt_locking_clauses')[0];
        self::assertNull((new ValueQueries($lowering))->values($source));
    }

    public function testQueryPreservesTheFullUncontextualizedSource(): void
    {
        $sql = 'INSERT INTO t (VALUES ROW(1)) ORDER BY ROW(2, 3) LIMIT absent FOR UPDATE OF missing';
        self::assertSame($sql, (new Semantics(Dialect::MySql))->analyze($sql)->toString());
    }

    public function testInactiveExcludesWrittenRowExpressions(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions($semantics->profile()), new \SqlSemantics\Lowering\Leaves(), $semantics->profile());
        $tree = $semantics->parser()->parse('INSERT INTO t VALUES ROW(ABS(1)) ORDER BY ABS()');
        $calls = $tree->find('function_call_generic');
        $inactive = (new ValueQueries($lowering))->inactive($tree);
        self::assertArrayNotHasKey(spl_object_id($calls[0]), $inactive);
        self::assertArrayHasKey(spl_object_id($calls[1]), $inactive);
    }
}
