<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\PartitionSelection;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable;

#[CoversClass(PartitionSelection::class)]
#[Small]
final class PartitionSelectionTest extends TestCase
{
    public function testCheckReportsAPartitionClauseOnAPlainTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');

        self::assertEquals([new UnpartitionedTable()], $semantics->analyze('SELECT a FROM t PARTITION (p0)', [$table])->facts->diagnostics);
        self::assertEquals([new UnpartitionedTable()], $semantics->analyze('INSERT INTO t PARTITION (p0) VALUES (1)', [$table])->facts->diagnostics);
    }

    public function testCheckAcceptsDeclaredPartitionsAndSubpartitionsInAnyCase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT) PARTITION BY RANGE (a) SUBPARTITION BY HASH (a) SUBPARTITIONS 2 (PARTITION r0 VALUES LESS THAN (10), PARTITION r1 VALUES LESS THAN MAXVALUE)');

        self::assertSame([], $semantics->analyze('SELECT a FROM t PARTITION (R0, r1sp1)', [$table])->facts->diagnostics);
        self::assertEquals([new UnknownPartition('p0sp0', 't')], $semantics->analyze('SELECT a FROM t PARTITION (r0, p0sp0)', [$table])->facts->diagnostics);
    }

    public function testCheckLeavesATableOfUnknownPartitionsAlone(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t PARTITION (p0)')->facts->diagnostics);
    }
}
