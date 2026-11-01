<?php

declare(strict_types=1);

namespace Tests\Unit\System\Privilege;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Privilege\Servers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Servers::class)]
#[Small]
final class ServersTest extends TestCase
{
    public function testRowsListsTheForeignServers(): void
    {
        $s = (new Instance())->connect();
        $s->query("CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'h', DATABASE 'd', USER 'u', PASSWORD 'p', PORT 3307, SOCKET 'k', OWNER 'o')");
        $s->query("CREATE SERVER t FOREIGN DATA WRAPPER mysql OPTIONS (USER 'x')");

        $result1 = $s->query('SELECT * FROM mysql.servers')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['s', 'h', 'd', 'u', 'p', '3307', 'k', 'mysql', 'o'], ['t', '', '', 'x', '', '0', '', 'mysql', '']], $result1->rows);
    }
}
