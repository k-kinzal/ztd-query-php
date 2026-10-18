<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use Deriver\Command\Application;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

#[CoversNothing]
final class ApplicationTest extends TestCase
{
    public function testRunProvidesHelpWithoutRequiringASelector(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        self::assertSame(0, $tester->run(['--help' => true]));
        self::assertStringContainsString('--config', $tester->getDisplay());
        self::assertStringContainsString('--include', $tester->getDisplay());
        self::assertStringContainsString('deliver [options]', $tester->getDisplay());
    }
}
