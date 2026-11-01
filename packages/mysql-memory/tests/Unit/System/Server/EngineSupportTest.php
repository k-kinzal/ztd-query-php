<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Server\EngineSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(EngineSupport::class)]
#[Small]
final class EngineSupportTest extends TestCase
{
    public function testRowsListsTheEnginesInTheServerOrder(): void
    {
        $s = (new Instance())->connect();

        $result1 = $s->query('SELECT ENGINE, SUPPORT FROM information_schema.ENGINES')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['ndbcluster', 'NO'], ['MEMORY', 'YES'], ['InnoDB', 'DEFAULT']], array_slice($result1->rows, 0, 3));
        $result2 = (new Instance('5.7.44'))->connect()->query('SELECT ENGINE, SUPPORT FROM information_schema.ENGINES')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['InnoDB', 'DEFAULT']], array_slice($result2->rows, 0, 1));
    }
}
