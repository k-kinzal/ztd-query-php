<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fuzz\Container\DisposableMySql;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Testcontainers\Containers\StartupCheckStrategy\StartupCheckStrategy;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class FixtureStartupTest extends TestCase
{
    public function testACompanionStartupFailureStopsBothAlreadyStartedContainers(): void
    {
        $failure = $this->createMock(StartupCheckStrategy::class);
        $failure->method('withLogger')->willReturnSelf();
        $failure->expects(self::once())->method('waitUntilStartupSuccessful')->willThrowException(new RuntimeException('Companion startup failed.'));
        $companion = new DisposableMySql('8.4.7');
        $companion->withStartupCheckStrategy($failure);
        $definition = new DisposableMySql('8.4.7', $companion);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Companion startup failed.');

            Testcontainers::run($definition);
        } finally {
            try {
                self::assertNotNull($definition->startedInstance);
                self::assertNotNull($companion->startedInstance);
                self::assertFalse($definition->startedInstance->isRunning());
                self::assertFalse($companion->startedInstance->isRunning());
            } finally {
                $definition->dispose();
            }
        }
    }
}
