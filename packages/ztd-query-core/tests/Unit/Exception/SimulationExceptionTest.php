<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\SimulationException;

#[CoversClass(SimulationException::class)]
final class SimulationExceptionTest extends TestCase
{
    public function testCarriesTheMessageItWasRefusedWith(): void
    {
        $exception = new class ('simulation failed') extends SimulationException {
        };

        self::assertSame('simulation failed', $exception->getMessage());
    }

    public function testCanBeCaughtAsTheOneKindOfFailureZtdProduces(): void
    {
        try {
            throw new class ('simulation failed') extends SimulationException {
            };
        } catch (SimulationException $refusal) {
            self::assertSame('simulation failed', $refusal->getMessage());

            return;
        }
    }
}
