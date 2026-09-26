<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Console\CoverageTable;
use Requirements\Console\Options;
use Requirements\Console\Reporter;
use Requirements\Console\SpecificationTable;
use Requirements\Console\Text;
use Requirements\Console\Verdict;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(Reporter::class)]
#[UsesClass(CoverageTable::class)]
#[UsesClass(Options::class)]
#[UsesClass(SpecificationTable::class)]
#[UsesClass(Text::class)]
#[UsesClass(Verdict::class)]
#[UsesClass(Fields::class)]
#[Small]
final class ReporterTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        putenv('COLUMNS=120');
    }

    #[Override]
    protected function tearDown(): void
    {
        putenv('COLUMNS');
    }

    /**
     * @param array<string, string|bool> $values
     * @param array<string, mixed> $report
     */
    #[DataProvider('providerRender')]
    public function testRenderWritesTheTitleTheCommandBodyAndTheVerdict(string $command, array $values, array $report, string $expected): void
    {
        $output = new BufferedOutput();
        (new Reporter())->render($report, new Options($command, $values), new ArrayInput([]), $output);
        self::assertSame($expected, preg_replace('/ +$/m', '', $output->fetch()));
    }

    /**
     * @return array<string, array{string, array<string, string|bool>, array<string, mixed>, string}>
     */
    public static function providerRender(): array
    {
        return [
            'lint' => ['lint', [], ['passed' => true, 'message' => '1 items validated.'], <<<'TEXT'

Requirements · lint
===================

 [OK] 1 items validated.


TEXT],
            'format with changes' => ['format', [], ['passed' => true, 'changed' => ["/p/<info>definition.yaml\x07"]], <<<'TEXT'

Requirements · format
=====================

 * /p/<info>definition.yaml

 [OK] Documents formatted.


TEXT],
            'format without changes' => ['format', ['check' => true], ['passed' => true, 'changed' => []], <<<'TEXT'

Requirements · format
=====================

 [OK] No formatting changes.


TEXT],
            'check' => ['check', [], ['passed' => false, 'mode' => 'configured', 'errors' => ['SPEC-001: quote not found.'], 'evidence' => []], <<<'TEXT'

Requirements · check
====================

 [ERROR] SPEC-001: quote not found.


TEXT],
            'coverage' => ['coverage', [], ['passed' => true, 'overall' => ['total' => 3, 'accounted' => 1, 'supported' => 1, 'unsupported' => 0, 'uncovered' => 2, 'percentage' => 33.3333], 'sources' => [], 'diff' => null, 'errors' => []], <<<'TEXT'

Requirements · coverage
=======================

 --------- ------- ----------- ----------- ------------- ----------- ----------
  Scope     Units   Accounted   Supported   Unsupported   Uncovered   Coverage
 --------- ------- ----------- ----------- ------------- ----------- ----------
  Overall   3       1           1           0             2           33.33%
 --------- ------- ----------- ----------- ------------- ----------- ----------

 ! [NOTE] Coverage applies only to the declared source scopes. Unsupported units are accounted for; tests are verified
 !        separately.

 [OK] Coverage gates passed.


TEXT],
            'spec' => ['spec', ['no-test' => true], ['passed' => true, 'no_test' => true, 'specifications' => ['SPEC-001' => ['statement' => 'The reader shall read.', 'kind' => 'specification', 'source' => 'manual', 'origin' => 'sourced', 'category' => '', 'labels' => [], 'reason' => '', 'status' => 'not-run', 'passed_targets' => null, 'total_targets' => 0, 'message' => '']], 'errors' => []], <<<'TEXT'

Requirements · spec
===================

+----------+------------------------+---------+-------+
| ID       | Statement              | Result  | Tests |
+----------+------------------------+---------+-------+
| SPEC-001 | The reader shall read. | not-run | -/0   |
|          | specification · manual |         |       |
+----------+------------------------+---------+-------+
 1 item(s). Tests: passed / linked targets. Use --json for execution counts and complete traceability records.

 [OK] Records displayed; tests were not run.


TEXT],
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    #[DataProvider('providerRenderRejected')]
    public function testRenderRejectsAReportWithoutTheShapeOfItsCommand(string $command, array $report, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new Reporter())->render($report, new Options($command, []), new ArrayInput([]), new BufferedOutput());
    }

    /**
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    public static function providerRenderRejected(): array
    {
        return [
            'format changes' => ['format', ['passed' => true, 'changed' => 'definition.yaml'], 'changed must be a list.'],
            'format duplicate changes' => ['format', ['passed' => true, 'changed' => ['a.yaml', 'a.yaml']], 'changed contains duplicates.'],
            'coverage sources' => ['coverage', ['passed' => true, 'overall' => [], 'sources' => 'manual', 'diff' => null], 'sources must be a mapping.'],
            'spec rows' => ['spec', ['passed' => true, 'specifications' => 'SPEC-001'], 'specifications must be a mapping.'],
            'check errors' => ['check', ['passed' => false, 'errors' => 'Broken.'], 'errors must be a list.'],
        ];
    }
}
