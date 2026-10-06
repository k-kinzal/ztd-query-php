<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ServerIds;

#[CoversClass(ServerIds::class)]
#[Medium]
final class ServerIdsTest extends TestCase
{
    public function testRenderWritesTheListAndTheEmptyList(): void
    {
        self::assertSame('CHANGE MASTER TO IGNORE_SERVER_IDS = (), IGNORE_SERVER_IDS = (2, 3)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('change master to ignore_server_ids = (), ignore_server_ids = (2,3)')->toString());
    }
}
