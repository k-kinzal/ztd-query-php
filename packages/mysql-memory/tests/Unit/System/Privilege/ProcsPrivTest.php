<?php

declare(strict_types=1);

namespace Tests\Unit\System\Privilege;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Privilege\ProcsPriv;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcsPriv::class)]
#[Small]
final class ProcsPrivTest extends TestCase
{
    public function testRowsListsTheRoutineGrants(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE PROCEDURE p() BEGIN END');
        $s->query("CREATE USER 'u'@'%'");
        $s->query("GRANT EXECUTE ON PROCEDURE d.p TO 'u'@'%' WITH GRANT OPTION");

        $result1 = $s->query('SELECT Host, Db, User, Routine_name, Routine_type, Grantor, Proc_priv FROM mysql.procs_priv')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['%', 'd', 'u', 'p', 'PROCEDURE', 'root@localhost', 'Execute,Grant']], $result1->rows);
    }
}
