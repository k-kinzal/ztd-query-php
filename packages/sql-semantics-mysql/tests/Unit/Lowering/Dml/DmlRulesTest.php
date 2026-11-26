<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Dml\DmlRules;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DmlRules::class)]
#[Medium]
final class DmlRulesTest extends TestCase
{
    public function testStatementLowersEveryStatementRule(): void
    {
        self::assertSame('INSERT INTO t VALUES (1)', (new Inspection('mysql-5.6.51'))->diagnose('insert t values (1)')->toString());
        self::assertSame('UPDATE t SET a = 1', (new Inspection())->diagnose('update t set a = 1')->toString());
        self::assertSame('DELETE FROM t', (new Inspection('mysql-5.7.44'))->diagnose('delete from t')->toString());
        self::assertSame('DO 1', (new Inspection())->diagnose('do 1')->toString());
        self::assertSame('HANDLER t OPEN', (new Inspection())->diagnose('handler t open')->toString());
        self::assertSame('CALL p', (new Inspection())->diagnose('call p')->toString());
        self::assertSame('IMPORT TABLE FROM \'f\'', (new Inspection())->diagnose('import table from \'f\'')->toString());
        self::assertSame('EXECUTE s', (new Inspection())->diagnose('execute s')->toString());
        self::assertSame('LOAD DATA INFILE \'f\' INTO TABLE t', (new Inspection())->diagnose('load data infile \'f\' into table t')->toString());
    }

    public function testDuplicateHandlingLowersReplaceAndIgnore(): void
    {
        self::assertSame('LOAD DATA INFILE \'f\' IGNORE INTO TABLE t', (new Inspection())->diagnose('load data infile \'f\' ignore into table t')->toString());
        self::assertSame('LOAD DATA INFILE \'f\' REPLACE INTO TABLE t', (new Inspection('mysql-5.6.51'))->diagnose('load data infile \'f\' replace into table t')->toString());
    }

    public function testFileFormatLowersTheOutfileFormat(): void
    {
        self::assertSame('SELECT 1 INTO OUTFILE \'f\' COLUMNS ENCLOSED BY \'"\'', (new Inspection())->diagnose('select 1 into outfile \'f\' fields enclosed by \'"\'')->toString());
    }

    public function testDeleteTargetsLowersTheTableNames(): void
    {
        self::assertSame('DELETE t, u FROM t, u', (new Inspection('mysql-5.7.44'))->diagnose('delete t.*, u from t, u')->toString());
        self::assertSame('SELECT 1 FROM t FOR UPDATE OF t', (new Inspection())->diagnose('select 1 from t for update of t')->toString());
    }

    public function testRowValuesLowersValuesAndDefault(): void
    {
        self::assertSame('INSERT INTO t VALUES (1, DEFAULT), ()', (new Inspection())->diagnose('insert t values (1, default), ()')->toString());
        self::assertSame('VALUES ROW(1), ROW()', (new Inspection())->diagnose('values row(1), row()')->toString());
    }

    public function testWildcardsKeepsTheStarOfALockedTable(): void
    {
        $operation = (new Inspection())->diagnose('SELECT (SELECT 1 FROM t FOR SHARE OF t.*) FROM t', []);

        self::assertSame('(SELECT 1 FROM t FOR SHARE OF t.*)', $operation->field(0)->name?->value);
    }
}
