<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(OptimizerHint::class)]
#[Medium]
final class OptimizerHintTest extends TestCase
{
    public function testNameAnswersTheHintOfEachForm(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT /*+ NO_MERGE() NO_ICP(t) NO_SEMIJOIN() SET_VAR(sql_mode = ANSI) */ 1')->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertSame(['NO_MERGE', 'NO_ICP', 'NO_SEMIJOIN', 'SET_VAR'], array_map(static fn (OptimizerHint $hint): string => $hint->name()->value, $statement->hints));
    }

    public function testTextWritesEachHintSoThatItReadsBack(): void
    {
        $sql = 'SELECT /*+ BKA(`t`, `u`@`q`) INDEX(@`q` `t` `i`, `PRIMARY`) SEMIJOIN(@`q` FIRSTMATCH) MAX_EXECUTION_TIME(5) RESOURCE_GROUP(`g`) SET_VAR(`sort_buffer_size` = 16) QB_NAME(`p`) */ 1';
        $statement = (new Semantics(Dialect::MySql))->analyze($sql)->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertSame($sql, (new Semantics(Dialect::MySql))->analyze($sql)->toString());
        self::assertContainsOnlyInstancesOf(OptimizerHint::class, $statement->hints);
    }
}
