<?php

declare(strict_types=1);

namespace Tests\Unit\Verification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Model\Item;
use Requirements\Model\Project;
use Requirements\Model\TestReference;
use Requirements\Test\BehatRunner;
use Requirements\Test\PhpUnitRunner;
use Requirements\Test\Registry;
use Requirements\Test\RunnerConfig;
use Requirements\Test\TestResult;
use Requirements\Verification\TestExecution;
use Tests\Fake\CountingRunner;
use Tests\Fake\ProjectDirectory;

#[CoversClass(TestExecution::class)]
#[UsesClass(Item::class)]
#[UsesClass(Project::class)]
#[UsesClass(TestReference::class)]
#[UsesClass(Registry::class)]
#[UsesClass(RunnerConfig::class)]
#[UsesClass(TestResult::class)]
#[UsesClass(PhpUnitRunner::class)]
#[UsesClass(BehatRunner::class)]
#[Small]
final class TestExecutionTest extends TestCase
{
    /**
     * @param list<string> $keys
     */
    #[DataProvider('providerModes')]
    public function testRunDeduplicatesEligibleTargetsAndNeverRunsUnsupportedItems(bool $all, array $keys, string $executed): void
    {
        $directory = new ProjectDirectory();
        $project = new Project($directory->directory, [], [], ['unit' => new RunnerConfig('custom', ['unused'], $directory->directory)], [], ['custom' => CountingRunner::class], 0.0, 0.0, [], []);
        $manual = new Item('MANUAL', 'specification', 'The reader shall read.', 'supported', null, [], [new TestReference('unit', 'shared', 'manual'), new TestReference('unit', 'slow', 'manual')], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $auto = new Item('AUTO', 'specification', 'The reader shall read.', 'supported', null, [], [new TestReference('unit', 'shared'), new TestReference('unit', 'shared')], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $unsupported = new Item('UNSUPPORTED', 'specification', 'The reader shall read.', 'unsupported', null, [], [new TestReference('unit', 'never', 'manual')], [], [], [], '', 'original', 'Not implemented.', 'definition.yaml', []);
        $requirement = new Item('REQ', 'requirement', 'The reader reads.', 'supported', null, [], [new TestReference('unit', 'never')], [], [], [], '', 'original', 'Upstream statement.', 'definition.yaml', []);
        $results = (new TestExecution())->run($project, ['MANUAL' => $manual, 'AUTO' => $auto, 'UNSUPPORTED' => $unsupported, 'REQ' => $requirement], $all);
        self::assertSame($keys, array_keys($results));
        self::assertSame($executed, $directory->read('executions.txt'));
    }

    /**
     * @return list<array{bool, list<string>, string}>
     */
    public static function providerModes(): array
    {
        return [
            [false, ["unit\0shared"], "shared\n"],
            [true, ["unit\0shared", "unit\0slow"], "shared\nslow\n"],
        ];
    }
}
