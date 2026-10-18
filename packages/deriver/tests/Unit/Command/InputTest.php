<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use Deriver\Command\DeriveCommand;
use Deriver\Command\Input;
use Deriver\Configuration\ReportFormat;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;

#[CoversNothing]
final class InputTest extends TestCase
{
    public function testConfigurationReadsRepeatedPatternsAndLanguageOverrides(): void
    {
        $input = new ArrayInput(['query' => 'answer#return', '--path' => ['src', 'app'], '--include' => ['*.php', '*.inc'], '--exclude' => ['Generated'], '--php-version' => '8.4', '--format' => 'json'], (new DeriveCommand())->getDefinition());
        $configuration = (new Input())->configuration($input);
        self::assertSame(['src', 'app'], $configuration->paths);
        self::assertSame(['*.php', '*.inc'], $configuration->include);
        self::assertSame(['Generated'], $configuration->exclude);
        self::assertSame('8.4', $configuration->phpVersion);
        self::assertSame(ReportFormat::Json, $configuration->format);
    }
}
