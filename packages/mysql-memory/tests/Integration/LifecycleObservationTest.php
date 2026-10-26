<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fuzz\Execution\Lifecycle;
use Fuzz\Execution\LifecycleAction;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class LifecycleObservationTest extends TestCase
{
    #[DataProvider('providerCommands')]
    public function testLifecycleComparisonKeepsTwoIndependentReferencesAndTheMemoryObservation(string $version, string $sql, LifecycleAction $action): void
    {
        $lifecycle = new Lifecycle();
        $comparison = $lifecycle->compare($sql, $action, $version);

        self::assertFalse($comparison->volatile, $comparison->referenceDifference ?? '');
        self::assertNull($comparison->difference, $comparison->difference ?? '');
        self::assertCount(3, $comparison->observations);
        self::assertSame($comparison->observations['native'], $comparison->observations['repeated']);
        self::assertCount(3, $lifecycle->observations);
        self::assertSame('complete', $lifecycle->observations[0]->phase);
        self::assertSame('complete', $lifecycle->observations[1]->phase);
        self::assertSame('complete', $lifecycle->observations[2]->phase);
        self::assertSame('disposed', $lifecycle->observations[0]->cleanup);
        self::assertSame('disposed', $lifecycle->observations[1]->cleanup);
        self::assertSame('disposed', $lifecycle->observations[2]->cleanup);
    }

    /**
     * @return iterable<string, array{string, string, LifecycleAction}>
     */
    public static function providerCommands(): iterable
    {
        foreach (\Container\MySqlRelease::versions() as $version) {
            if (!str_starts_with($version, '5.6.')) {
                yield $version . ' shutdown' => [$version, '/* fixture */ SHUTDOWN', LifecycleAction::Shutdown];
            }
            if (!str_starts_with($version, '5.')) {
                yield $version . ' restart' => [$version, 'RESTART /* fixture */', LifecycleAction::Restart];
            }
        }
    }
}
