<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Performance\GlobalVariables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(GlobalVariables::class)]
#[Small]
final class GlobalVariablesTest extends TestCase
{
    public function testRowsListsTheGlobalValues(): void
    {
        $s = (new Instance())->connect();
        $s->query('SET GLOBAL max_connections = 99');

        $result1 = $s->query("SELECT * FROM performance_schema.global_variables WHERE VARIABLE_NAME = 'max_connections'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['max_connections', '99']], $result1->rows);
        $result2 = (new Instance('5.6.51'))->connect()->query("SELECT * FROM information_schema.GLOBAL_VARIABLES WHERE VARIABLE_NAME = 'autocommit'")[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['AUTOCOMMIT', 'ON']], $result2->rows);
    }

    public function testRowsIsDisabledInInformationSchemaOfMySql57(): void
    {
        $s = (new Instance('5.7.44'))->connect();

        $this->expectExceptionMessage("The 'INFORMATION_SCHEMA.GLOBAL_VARIABLES' feature is disabled; see the documentation for 'show_compatibility_56'");
        $s->query('SELECT * FROM information_schema.GLOBAL_VARIABLES');
    }
}
