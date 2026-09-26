<?php

declare(strict_types=1);

namespace Tests\Unit\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Test\RunnerConfig;
use Requirements\Test\RunnerExtension;
use Requirements\Test\TestResult;
use Tests\Fake\CountingRunner;
use Tests\Fake\ProjectDirectory;

#[CoversClass(RunnerExtension::class)]
#[UsesClass(RunnerConfig::class)]
#[UsesClass(TestResult::class)]
#[Small]
final class RunnerExtensionTest extends TestCase
{
    public function testRunReportsTheResultOfTheTarget(): void
    {
        $project = new ProjectDirectory();
        $runner = new CountingRunner();
        $config = new RunnerConfig('custom', ['example'], $project->directory);
        $result = $runner->run($config, 'shared');
        self::assertSame(['status' => 'passed', 'tests' => 1, 'message' => ''], $result->toArray());
        self::assertSame(['status' => 'passed', 'tests' => 0, 'message' => ''], $runner->run($config, 'empty')->toArray());
        self::assertSame("shared\nempty\n", $project->read('executions.txt'));
    }
}
