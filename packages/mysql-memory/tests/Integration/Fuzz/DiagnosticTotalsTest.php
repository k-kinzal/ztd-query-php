<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\DiagnosticObservation;
use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class DiagnosticTotalsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerStatements(): iterable
    {
        $handlers = ['unhandled' => '', 'plain' => 'RESIGNAL', 'new state inherited message' => "RESIGNAL SQLSTATE '45001'", 'new state' => "RESIGNAL SQLSTATE '45001' SET MESSAGE_TEXT='replacement'"];
        $bodies = ['signal' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='original'", 'warning and overflow' => "SELECT CAST('1a' AS SIGNED),CAST(1 AS UNSIGNED)-2"];
        foreach ([0, 1, 2] as $limit) {
            foreach ($handlers as $handlerName => $handler) {
                foreach ($bodies as $bodyName => $body) {
                    yield $handlerName . ' ' . $bodyName . ' limit ' . $limit => [$handler, $body, $limit];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testResignalPreservesTheErrorAndBoundedDiagnosticTotals(string $handler, string $body, int $limit): void
    {
        [$target] = Servers::shared();
        $target->repair($target->guard());
        $target->repair($target->memoryGuard());
        $expected = DiagnosticObservation::observe($target->native, $target->nativeUser, $target->nativePassword, $handler, $body, $limit);
        $actual = DiagnosticObservation::observe($target->memory, 'root', '', $handler, $body, $limit);

        self::assertSame($expected, $actual);
    }
}
