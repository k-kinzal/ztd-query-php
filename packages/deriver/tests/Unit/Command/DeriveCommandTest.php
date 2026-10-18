<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use Deriver\Command\DeriveCommand;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversNothing]
final class DeriveCommandTest extends TestCase
{
    public function testExecuteReportsInvalidFormatsOnlyOnStandardError(): void
    {
        $tester = new CommandTester(new DeriveCommand());
        self::assertSame(2, $tester->execute(['query' => 'answer#return', '--format' => 'xml'], ['capture_stderr_separately' => true]));
        self::assertSame('', $tester->getDisplay());
        self::assertStringContainsString('format must be text or json', $tester->getErrorOutput());
    }

    public function testExecuteReportsMissingConfigurations(): void
    {
        $tester = new CommandTester(new DeriveCommand());
        self::assertSame(2, $tester->execute(['query' => 'answer#return', '--config' => '/nonexistent-deriver-config/deliver.yaml'], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('readable local YAML', $tester->getErrorOutput());
    }
}
