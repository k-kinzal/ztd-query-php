<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\MySqlRelease;
use Fuzz\Execution\LifecycleAction;
use Fuzz\Target\ExecutionTarget;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class LifecycleClassificationTest extends TestCase
{
    #[DataProvider('providerStatements')]
    public function testOnlyGrammarRecognizedTopLevelCommandsSelectLifecycleObservation(string $version, string $sql, ?LifecycleAction $expected): void
    {
        $target = new ExecutionTarget('unused', 'root', '', 'unused', version: $version);

        self::assertSame($expected, $target->lifecycleAction($sql));
    }

    /**
     * @return iterable<string, array{string, string, LifecycleAction|null}>
     */
    public static function providerStatements(): iterable
    {
        foreach (MySqlRelease::versions() as $version) {
            $shutdown = str_starts_with($version, '5.6.') ? null : LifecycleAction::Shutdown;
            $restart = str_starts_with($version, '5.') ? null : LifecycleAction::Restart;
            foreach ([
                ['SHUTDOWN', $shutdown],
                ["-- before\n ShUtDoWn /* after */ ;", $shutdown],
                ['/*!50709 SHUTDOWN */', $shutdown],
                ['RESTART', $restart],
                ['/* before */ ReStArT ; /* after */', $restart],
                ["SELECT 'SHUTDOWN' /* RESTART */", null],
                ['CREATE PROCEDURE p() BEGIN SHUTDOWN; END', null],
                ['CREATE PROCEDURE p() BEGIN RESTART; END', null],
                ['SELECT 1; SHUTDOWN', null],
            ] as [$sql, $action]) {
                yield $version . ' ' . $sql => [$version, $sql, $action];
            }
        }
    }
}
