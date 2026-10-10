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
final class ProcessTimeTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function providerClocks(): iterable
    {
        yield 'past statement clock' => [1700000000];
        yield 'future statement clock' => [2100000000];
    }

    #[DataProvider('providerClocks')]
    public function testElapsedTimeFollowsThePinnedStatementClock(int $timestamp): void
    {
        [$target] = Servers::shared();
        $result = $target->compare('SET timestamp=' . $timestamp . '; SELECT ABS(`TIME`-(UNIX_TIMESTAMP(SYSDATE())-@@timestamp))<=1 AS follows_clock FROM information_schema.PROCESSLIST WHERE ID=CONNECTION_ID()');

        self::assertFalse($result->volatile);
        self::assertNull($result->difference, (string) $result->difference);
    }

    public function testProcessHostIncludesTheTcpSourcePort(): void
    {
        [$target] = Servers::shared();
        $result = $target->compare("SELECT HOST LIKE CONCAT(SUBSTRING_INDEX(USER(),'@',-1),':%') AS host_matches, CAST(SUBSTRING_INDEX(HOST,':',-1) AS UNSIGNED) BETWEEN 1 AND 65535 AS source_port FROM information_schema.PROCESSLIST WHERE ID=CONNECTION_ID()");

        self::assertFalse($result->volatile);
        self::assertNull($result->difference, (string) $result->difference);
    }
}
