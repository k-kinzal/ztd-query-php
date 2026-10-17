<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\RefreshObservation;
use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class RefreshCommandTest extends TestCase
{
    /**
     * @return iterable<string, array{int, list<string>}>
     */
    public static function providerFlags(): iterable
    {
        foreach ([0, 1, 2, 4, 8, 16, 32, 64, 128, 255] as $flags) {
            yield 'mask ' . $flags => [$flags, []];
            yield 'mask ' . $flags . ' after warnings' => [$flags, ["SELECT 'a'+1,'b'+1"]];
            yield 'mask ' . $flags . ' after reading warnings' => [$flags, ["SELECT 'a'+1,'b'+1", 'SHOW WARNINGS']];
            yield 'mask ' . $flags . ' with no stored warnings' => [$flags, ['SET max_error_count=0', "SELECT 'a'+1,'b'+1"]];
            yield 'mask ' . $flags . ' with dropped warnings' => [$flags, ['SET max_error_count=1', "SELECT 'a'+1,'b'+1"]];
        }
    }

    /**
     * @param list<string> $beforeRefresh
     */
    #[DataProvider('providerFlags')]
    public function testRefreshMatchesWarningsCountersAndTransactionEffects(int $flags, array $beforeRefresh): void
    {
        [$target] = Servers::shared();
        $target->repair($target->guard());
        $target->repair($target->memoryGuard());
        $expected = RefreshObservation::observe($target->native, $target->nativeUser, $target->nativePassword, $flags, $beforeRefresh);
        $actual = RefreshObservation::observe($target->memory, 'root', '', $flags, $beforeRefresh);

        self::assertSame($expected, $actual);
    }

}
