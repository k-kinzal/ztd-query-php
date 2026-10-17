<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class EventDaemonTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerChanges(): iterable
    {
        $start = 'SET @scheduler=@@GLOBAL.event_scheduler; SET GLOBAL event_scheduler=OFF; DO SLEEP(0.1); SET GLOBAL event_scheduler=ON; DO SLEEP(0.1); ';
        $inspect = "SELECT USER, HOST, DB, COMMAND, STATE, INFO, ID>0 AS positive_id FROM information_schema.PROCESSLIST WHERE USER='event_scheduler'; ";
        $restore = 'SET GLOBAL event_scheduler=@scheduler';
        yield 'empty queue' => [$start . $inspect . $restore];
        yield 'stopped daemon is absent' => [$start . 'SET GLOBAL event_scheduler=OFF; DO SLEEP(0.1); ' . $inspect . $restore];
        $event = 'SET timestamp=0; CREATE EVENT later ON SCHEDULE AT CURRENT_TIMESTAMP + INTERVAL 1 DAY DO DO 1; DO SLEEP(0.1); ';
        yield 'enabled event wakes daemon' => [$start . $event . $inspect . $restore];
        yield 'disabled event does not immediately wake daemon' => [$start . $event . 'ALTER EVENT later DISABLE; DO SLEEP(0.1); ' . $inspect . $restore];
        yield 'removed event does not immediately wake daemon' => [$start . $event . 'DROP EVENT later; DO SLEEP(0.1); ' . $inspect . $restore];
    }

    #[DataProvider('providerChanges')]
    public function testDaemonStateMatchesTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $result = $target->compare($sql);

        self::assertFalse($result->volatile);
        self::assertNull($result->difference, (string) $result->difference);
    }
}
