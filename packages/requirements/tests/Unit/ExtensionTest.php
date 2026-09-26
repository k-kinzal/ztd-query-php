<?php

declare(strict_types=1);

namespace Requirements\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Requirements\Config\Loader;
use Requirements\Report\Analyzer;
use Requirements\Test\Verifier;
use Requirements\Tests\Support\CountingRunner;
use Requirements\Tests\Support\MemorySource;
use Requirements\Tests\Support\Workspace;
use Symfony\Component\Process\Process;

final class ExtensionTest extends TestCase
{
    public function testCustomSourcesAndRunnersUseTheSamePipelineAndDeduplicateTests(): void
    {
        $workspace = new Workspace();
        $workspace->write('requirements.yaml', [
            'version' => 1,
            'definitions' => ['definition.yaml'],
            'extensions' => ['sources' => ['service' => MemorySource::class], 'runners' => ['custom' => CountingRunner::class]],
            'runners' => ['example' => ['extension' => 'custom', 'command' => ['example']]],
        ]);
        $item = ['id' => 'SPEC-1', 'statement' => 'The service shall retain its message.', 'evidence' => [['selector' => 'message:1', 'quote' => 'A service message.']], 'tests' => [['runner' => 'example', 'target' => 'shared']]];
        $second = $item;
        $second['id'] = 'SPEC-2';
        $unsupported = $item;
        $unsupported['id'] = 'SPEC-3';
        $unsupported['status'] = 'unsupported';
        $unsupported['reason'] = 'The external system owns this behavior.';
        $unsupported['tests'] = [['runner' => 'example', 'target' => 'never']];
        $workspace->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'service', 'uri' => 'service:channel', 'format' => 'service', 'selector' => '*'], 'items' => [$item, $second, $unsupported]]);
        $project = (new Loader())->load($workspace->directory . '/requirements.yaml');
        $analysis = (new Analyzer())->analyze($project);
        self::assertSame([], $analysis->errors);
        self::assertSame(100.0, $analysis->summary()['percentage']);
        $results = (new Verifier())->verify($project, $project->items);
        self::assertSame('passed', $results['SPEC-1']->status);
        self::assertSame('passed', $results['SPEC-2']->status);
        self::assertSame(1, $results['SPEC-1']->passedTargets);
        self::assertSame(1, $results['SPEC-2']->totalTargets);
        self::assertSame('unsupported', $results['SPEC-3']->status);
        self::assertSame("shared\n", file_get_contents($workspace->directory . '/executions.txt'));
    }

    public function testAZeroCaseSuccessDoesNotCountAsAPassingTarget(): void
    {
        $workspace = new Workspace();
        $workspace->write('requirements.yaml', [
            'version' => 1,
            'definitions' => ['definition.yaml'],
            'extensions' => ['runners' => ['custom' => CountingRunner::class]],
            'runners' => ['example' => ['extension' => 'custom', 'command' => ['example']]],
        ]);
        $workspace->write('definition.yaml', ['version' => 1, 'source' => null, 'items' => [[
            'id' => 'SPEC-1', 'statement' => 'The reader shall retain positions.', 'origin' => 'original', 'reason' => 'Support editors.',
            'tests' => [['runner' => 'example', 'target' => 'empty']],
        ]]]);
        $project = (new Loader())->load($workspace->directory . '/requirements.yaml');
        $result = (new Verifier())->verify($project, $project->items)['SPEC-1'];
        self::assertSame('failed', $result->status);
        self::assertSame(0, $result->passedTargets);
        self::assertSame(1, $result->totalTargets);
        self::assertSame(0, $result->tests);
    }

    public function testDocumentedExtensionsExecuteThroughTheRealCli(): void
    {
        $package = dirname(__DIR__, 2);
        foreach (['lint', 'check', 'coverage', 'spec'] as $command) {
            $process = new Process([PHP_BINARY, $package . '/bin/requirements', $command, '--config', $package . '/tests/Fixtures/Examples/Extensions/requirements.yaml', '--json']);
            self::assertSame(0, $process->run(), $process->getOutput() . $process->getErrorOutput());
            self::assertStringContainsString('"passed": true', $process->getOutput());
            if ($command === 'spec') {
                self::assertStringContainsString('"tests": 1', $process->getOutput());
            }
        }
    }

}
