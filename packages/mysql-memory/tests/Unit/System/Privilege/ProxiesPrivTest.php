<?php

declare(strict_types=1);

namespace Tests\Unit\System\Privilege;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Privilege\ProxiesPriv;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProxiesPriv::class)]
#[Small]
final class ProxiesPrivTest extends TestCase
{
    public function testRowsListsTheProxyGrants(): void
    {
        $s = (new Instance())->connect();

        $result1 = $s->query('SELECT Host, User, Proxied_host, Proxied_user, With_grant FROM mysql.proxies_priv')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['localhost', 'root', '', '', '1']], $result1->rows);
    }
}
