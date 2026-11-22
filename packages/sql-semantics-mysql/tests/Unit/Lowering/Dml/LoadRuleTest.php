<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Dml\LoadRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LoadRule::class)]
#[Medium]
final class LoadRuleTest extends TestCase
{
    public function testStatementLowersEveryGeneration(): void
    {
        self::assertSame('LOAD DATA INFILE \'f\' INTO TABLE t', (new Inspection('mysql-5.6.51'))->inspect('load data infile \'f\' into table t')->toString());
        self::assertSame('LOAD DATA S3 \'f\' INTO TABLE t', (new Inspection('mysql-9.1.0'))->inspect('load data from s3 \'f\' into table t')->toString());
    }

    public function testStatementRejectsAnotherWordThanCount(): void
    {
        $this->expectExceptionMessage('Syntax error: COUNT expected before the number of files.');

        (new Inspection())->inspect("LOAD DATA INFILE 'f' many 2 INTO TABLE t");
    }

    public function testFormatLowersDataAndXml(): void
    {
        self::assertSame('LOAD XML INFILE \'f\' INTO TABLE t', (new Inspection())->inspect('load xml infile \'f\' into table t')->toString());
    }

    public function testLockLowersTheModifiers(): void
    {
        self::assertSame('LOAD DATA LOW_PRIORITY INFILE \'f\' INTO TABLE t', (new Inspection())->inspect('load data low_priority infile \'f\' into table t')->toString());
        self::assertSame('LOAD DATA CONCURRENT INFILE \'f\' INTO TABLE t', (new Inspection())->inspect('load data concurrent infile \'f\' into table t')->toString());
    }

    public function testSourceLowersTheSourceKinds(): void
    {
        self::assertSame('LOAD DATA URL \'u\' INTO TABLE t', (new Inspection('mysql-8.2.0'))->inspect('load data url \'u\' into table t')->toString());
    }

    public function testFlagLowersTheOptionalWords(): void
    {
        self::assertSame('LOAD DATA LOCAL INFILE \'f\' INTO TABLE t ALGORITHM = BULK', (new Inspection())->inspect('load data from local infile \'f\' into table t algorithm = bulk')->toString());
    }

    public function testInputLowersTheSource(): void
    {
        self::assertSame('LOAD DATA LOCAL URL \'u\' `count` 3 INTO TABLE t', (new Inspection('mysql-8.2.0'))->inspect('load data local url \'u\' count 3 into table t')->toString());
    }

    public function testTaggedLowersTheRowTag(): void
    {
        self::assertSame('LOAD XML INFILE \'f\' INTO TABLE t ROWS IDENTIFIED BY \'<r>\'', (new Inspection())->inspect('load xml infile \'f\' into table t rows identified by \'<r>\'')->toString());
    }

    public function testIgnoredLowersTheCount(): void
    {
        self::assertSame('LOAD DATA INFILE \'f\' INTO TABLE t IGNORE 3 LINES', (new Inspection())->inspect('load data infile \'f\' into table t ignore 3 rows')->toString());
    }

    public function testColumnsLowersColumnsAndVariables(): void
    {
        self::assertSame('LOAD DATA INFILE \'f\' INTO TABLE t (a, @b, t.c)', (new Inspection())->inspect('load data infile \'f\' into table t (a, @b, t.c)')->toString());
        self::assertSame('LOAD DATA INFILE \'f\' INTO TABLE t', (new Inspection())->inspect('load data infile \'f\' into table t ()')->toString());
    }

    public function testAssignmentsLowersBothGenerations(): void
    {
        self::assertSame('LOAD DATA INFILE \'f\' INTO TABLE t SET a = 1', (new Inspection('mysql-5.7.44'))->inspect('load data infile \'f\' into table t set a = 1')->toString());
        self::assertSame('LOAD DATA INFILE \'f\' INTO TABLE t SET a = DEFAULT', (new Inspection())->inspect('load data infile \'f\' into table t set a = default')->toString());
    }

    public function testBulkLowersTheOptions(): void
    {
        self::assertSame('LOAD DATA INFILE \'f\' INTO TABLE t PARALLEL = 2 MEMORY = 1024', (new Inspection('mysql-8.3.0'))->inspect('load data infile \'f\' into table t parallel = 2 memory = 1024')->toString());
    }
}
