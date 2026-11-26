<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ServerIds;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ServerIds::class)]
#[Medium]
final class ServerIdsTest extends TestCase
{
    public function testRenderWritesTheListAndTheEmptyList(): void
    {
        self::assertSame('CHANGE MASTER TO IGNORE_SERVER_IDS = (), IGNORE_SERVER_IDS = (2, 3)', (new Inspection('mysql-5.6.51'))->inspect('change master to ignore_server_ids = (), ignore_server_ids = (2,3)')->toString());
    }
}
