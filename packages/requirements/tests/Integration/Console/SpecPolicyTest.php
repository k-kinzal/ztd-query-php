<?php

declare(strict_types=1);

namespace Tests\Integration\Console;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Console\Application;
use Requirements\Input\Fields;
use Tests\Fake\CommandLine as Cli;
use Tests\Fake\CountingRunner;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Application::class)]
#[UsesClass(Fields::class)]
#[Large]
final class SpecPolicyTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $options
     * @param array<string, string> $statuses
     * @throws JsonException
     */
    #[DataProvider('providerPolicies')]
    public function testPoliciesControlExitCodesAndExecutedTargets(array $items, array $options, int $exitCode, array $statuses, string $executed): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', [
            'version' => 1,
            'definitions' => ['definition.yaml'],
            'extensions' => ['runners' => ['counting' => CountingRunner::class]],
            'runners' => ['unit' => ['extension' => 'counting', 'command' => ['unused']]],
        ]);
        $project->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => $items]);
        $process = Cli::run(['spec', '--json', ...$options], $project->directory);
        self::assertSame($exitCode, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        $report = Fields::mapping(json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR), 'report');
        self::assertSame($exitCode === 0, $report['passed']);
        self::assertSame($statuses, array_column(Fields::mapping($report['specifications'], 'rows'), 'status', 'id'));
        self::assertSame(in_array('--all', $options, true), $report['all']);
        self::assertSame(in_array('--strict', $options, true), $report['strict']);
        self::assertSame($executed, $project->read('executions.txt'));
        self::assertSame($executed !== '', is_file($project->path('executions.txt')));
    }

    /**
     * @return array<string, array{list<array<string, mixed>>, list<string>, int, array<string, string>, string}>
     */
    public static function providerPolicies(): array
    {
        $base = ProjectDirectory::item();
        $unlinked = [...$base, 'id' => 'UNLINKED'];
        $unit = ['runner' => 'unit', 'target' => 'fast'];
        $manual = [...$unit, 'run' => 'manual'];
        $automatic = [...$base, 'id' => 'AUTO', 'tests' => [$unit, $manual]];
        $deferred = [...$base, 'id' => 'MANUAL', 'tests' => [$manual, $manual]];
        $mixed = [...$base, 'id' => 'MIXED', 'tests' => [$unit, ['runner' => 'unit', 'target' => 'empty', 'run' => 'manual']]];
        $failure = [...$base, 'id' => 'FAILURE', 'tests' => [['runner' => 'unit', 'target' => 'empty'], $manual]];
        $unsupported = [...$base, 'id' => 'UNSUPPORTED', 'status' => 'unsupported', 'reason' => 'Not implemented.', 'tests' => [['runner' => 'unit', 'target' => 'never', 'run' => 'manual']]];
        $requirement = [...$base, 'id' => 'REQ', 'kind' => 'requirement'];
        return [
            'unlinked allowed' => [[$unlinked], [], 0, ['UNLINKED' => 'unverified'], ''],
            'strict unlinked' => [[$unlinked], ['--strict'], 1, ['UNLINKED' => 'unverified'], ''],
            'all does not invent links' => [[$unlinked], ['--all'], 0, ['UNLINKED' => 'unverified'], ''],
            'all strict still needs links' => [[$unlinked], ['--all', '--strict'], 1, ['UNLINKED' => 'unverified'], ''],
            'no test unlinked' => [[$unlinked], ['--no-test'], 0, ['UNLINKED' => 'not-run'], ''],
            'no test strict' => [[$unlinked], ['--no-test', '--strict'], 1, ['UNLINKED' => 'not-run'], ''],
            'empty selection' => [[$unlinked], ['--id=MISSING'], 0, [], ''],
            'strict empty selection' => [[$unlinked], ['--id=MISSING', '--strict'], 1, [], ''],
            'manual only' => [[$deferred], [], 0, ['MANUAL' => 'deferred'], ''],
            'strict permits manual' => [[$deferred], ['--strict'], 0, ['MANUAL' => 'deferred'], ''],
            'all executes manual once' => [[$deferred], ['--all', '--strict'], 0, ['MANUAL' => 'passed'], "fast\n"],
            'mixed deferred' => [[$mixed], [], 0, ['MIXED' => 'deferred'], "fast\n"],
            'all exposes failure' => [[$mixed], ['--all'], 1, ['MIXED' => 'failed'], "fast\nempty\n"],
            'failure beats deferred' => [[$failure], [], 1, ['FAILURE' => 'failed'], "empty\n"],
            'no test beats all' => [[$mixed], ['--no-test', '--all', '--strict'], 0, ['MIXED' => 'not-run'], ''],
            'shared manual first' => [[$deferred, $automatic], [], 0, ['MANUAL' => 'passed', 'AUTO' => 'passed'], "fast\n"],
            'shared automatic first' => [[$automatic, $deferred], [], 0, ['AUTO' => 'passed', 'MANUAL' => 'passed'], "fast\n"],
            'filter excludes automatic reference' => [[$automatic, $deferred], ['--id=MANUAL'], 0, ['MANUAL' => 'deferred'], ''],
            'all respects filter' => [[$failure, $deferred], ['--id=MANUAL', '--all'], 0, ['MANUAL' => 'passed'], "fast\n"],
            'all respects support' => [[$unsupported, $requirement], ['--all', '--strict'], 0, ['UNSUPPORTED' => 'unsupported', 'REQ' => 'not-applicable'], ''],
        ];
    }
}
