<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\TargetRule;

#[CoversClass(TargetRule::class)]
#[Medium]
final class TargetRuleTest extends TestCase
{
    public function testTablesLowersEveryTableName(): void
    {
        self::assertSame('DELETE FROM t, db.u USING t, db.u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('delete from t, db.u.* using t, db.u')->toString());
    }

    public function testDuplicatesLowersReplaceAndIgnore(): void
    {
        self::assertSame('LOAD DATA INFILE \'f\' REPLACE INTO TABLE t', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('load data infile \'f\' replace into table t')->toString());
    }

    public function testWildcardsTellsTheTablesWrittenWithAStar(): void
    {
        self::assertSame('SELECT 1 FROM t, u FOR UPDATE OF t.*, u', (new Semantics(Dialect::MySql))->analyze('select 1 from t, u for update of t.*, u')->toString());
    }
}
