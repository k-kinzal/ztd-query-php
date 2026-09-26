<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Console\ItemRecord;
use Requirements\Console\Options;
use Requirements\Console\SpecificationReport;
use Requirements\Model\Item;
use Requirements\Model\Project;
use Requirements\Verification\VerificationResult;
use Requirements\Verification\Verifier;

#[CoversClass(SpecificationReport::class)]
#[UsesClass(ItemRecord::class)]
#[UsesClass(Options::class)]
#[UsesClass(Item::class)]
#[UsesClass(Project::class)]
#[UsesClass(Verifier::class)]
#[UsesClass(VerificationResult::class)]
#[Small]
final class SpecificationReportTest extends TestCase
{
    /**
     * @param array<string, string|bool> $options
     * @param list<string> $errors
     */
    #[DataProvider('providerGates')]
    public function testGenerateChecksLinkageIndependentlyOfExecution(array $options, bool $passed, array $errors): void
    {
        $item = new Item('SPEC-1', 'specification', 'The reader shall read.', 'supported', null, [], [], [], [], [], '', 'original', 'Support editors.', 'definition.yaml', []);
        $project = new Project('.', ['SPEC-1' => $item], [], [], [], [], 0.0, 0.0, [], []);
        $report = (new SpecificationReport())->generate($project, new Options('spec', ['no-test' => true, ...$options]));
        self::assertSame($passed, $report['passed']);
        self::assertSame($errors, $report['errors']);
        self::assertTrue($report['no_test']);
    }

    /**
     * @return list<array{array<string, string|bool>, bool, list<string>}>
     */
    public static function providerGates(): array
    {
        return [
            [[], true, []],
            [['strict' => true], false, ['SPEC-1: No tests linked.']],
            [['id' => 'MISSING'], true, []],
            [['id' => 'MISSING', 'strict' => true], false, ['No specifications or requirements selected.']],
        ];
    }
}
