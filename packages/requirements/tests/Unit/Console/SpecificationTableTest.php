<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Console\SpecificationTable;
use Requirements\Console\Text;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CoversClass(SpecificationTable::class)]
#[UsesClass(Text::class)]
#[UsesClass(Fields::class)]
#[Small]
final class SpecificationTableTest extends TestCase
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

    public function testRenderShowsOneRowPerItemAndTheFailureMessages(): void
    {
        $row = ['statement' => 'The reader shall preserve positions.', 'kind' => 'specification', 'source' => 'manual', 'origin' => 'sourced', 'category' => 'reader', 'labels' => ['diagnostics', 'reader'], 'reason' => 'Keep offsets.', 'status' => 'passed', 'passed_targets' => 2, 'total_targets' => 2, 'message' => ''];
        $output = new BufferedOutput();
        (new SpecificationTable())->render(new SymfonyStyle(new ArrayInput([]), $output), $output, ['specifications' => [
            'SPEC-001' => $row,
            'ORIGINAL-001' => [...$row, 'source' => null, 'origin' => 'original', 'category' => '', 'labels' => [], 'reason' => '', 'status' => 'not-run', 'passed_targets' => null, 'total_targets' => 3],
            'FAILED' => [...$row, 'status' => 'failed', 'passed_targets' => 0, 'total_targets' => 1, 'message' => 'Sample\\PassingTest::testFailure: Expected <comment>failure</comment>.'],
        ]]);
        self::assertSame(<<<'TEXT'
+--------------+--------------------------------------+---------+-------+
| ID           | Statement                            | Result  | Tests |
+--------------+--------------------------------------+---------+-------+
| SPEC-001     | The reader shall preserve positions. | passed  | 2/2   |
|              | specification · manual · reader      |         |       |
|              | Labels: diagnostics, reader          |         |       |
|              | Reason: Keep offsets.                |         |       |
| ORIGINAL-001 | The reader shall preserve positions. | not-run | -/3   |
|              | specification · original             |         |       |
| FAILED       | The reader shall preserve positions. | failed  | 0/1   |
|              | specification · manual · reader      |         |       |
|              | Labels: diagnostics, reader          |         |       |
|              | Reason: Keep offsets.                |         |       |
+--------------+--------------------------------------+---------+-------+
 3 item(s). Tests: passed / linked targets. Use --json for execution counts and complete traceability records.
 FAILED: Sample\PassingTest::testFailure: Expected <comment>failure</comment>.

TEXT, preg_replace('/ +$/m', '', $output->fetch()));
    }

    public function testRenderEscapesMarkupAndRemovesControlCharacters(): void
    {
        $output = new BufferedOutput();
        (new SpecificationTable())->render(new SymfonyStyle(new ArrayInput([]), $output), $output, ['specifications' => [
            '<info>ID</info>' => ['statement' => "The\x07 reader.", 'kind' => "kind\x1b", 'source' => null, 'origin' => "original\x00", 'category' => "cat\x7f", 'labels' => ["label\x01"], 'reason' => "why\x0b", 'status' => "failed\x08", 'passed_targets' => 1, 'total_targets' => 2, 'message' => "<comment>Broken</comment>\x1b[0m"],
        ]]);
        self::assertSame(<<<'TEXT'
+-----------------+-----------------------+--------+-------+
| ID              | Statement             | Result | Tests |
+-----------------+-----------------------+--------+-------+
| <info>ID</info> | The reader.           | failed | 1/2   |
|                 | kind · original · cat |        |       |
|                 | Labels: label         |        |       |
|                 | Reason: why           |        |       |
+-----------------+-----------------------+--------+-------+
 1 item(s). Tests: passed / linked targets. Use --json for execution counts and complete traceability records.
 <info>ID</info>: <comment>Broken</comment>[0m

TEXT, preg_replace('/ +$/m', '', $output->fetch()));
    }

    public function testRenderShowsAnEmptyTable(): void
    {
        $output = new BufferedOutput();
        (new SpecificationTable())->render(new SymfonyStyle(new ArrayInput([]), $output), $output, ['specifications' => []]);
        self::assertSame(<<<'TEXT'
+----+-----------+--------+-------+
| ID | Statement | Result | Tests |
+----+-----------+--------+-------+
 0 item(s). Tests: passed / linked targets. Use --json for execution counts and complete traceability records.

TEXT, preg_replace('/ +$/m', '', $output->fetch()));
    }

    /**
     * @param array<string, mixed> $report
     */
    #[DataProvider('providerRenderRejected')]
    public function testRenderRejectsRowsThatAreNotSpecificationRecords(array $report, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        $output = new BufferedOutput();
        (new SpecificationTable())->render(new SymfonyStyle(new ArrayInput([]), $output), $output, $report);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function providerRenderRejected(): array
    {
        return [
            'specifications list' => [['specifications' => 'SPEC-001'], 'specifications must be a mapping.'],
            'integer keys' => [['specifications' => [['statement' => 'The reader shall read.']]], 'specifications must have string keys.'],
            'row' => [['specifications' => ['SPEC-001' => 'passed']], 'specification must be a mapping.'],
            'labels' => [['specifications' => ['SPEC-001' => ['statement' => 'The reader shall read.', 'kind' => 'specification', 'source' => null, 'origin' => 'original', 'category' => '', 'labels' => 'grammar']]], 'labels must be a list.'],
        ];
    }

    #[DataProvider('providerTableWidths')]
    public function testTableWrapsTheStatementToTheTerminalWidth(string $columns, string $id, int $expected): void
    {
        putenv('COLUMNS=' . $columns);
        $output = new BufferedOutput();
        (new SpecificationTable())->table($output, ['ID', 'Statement', 'Result', 'Tests'], [[$id, str_repeat('x', 200), 'ok', '1/1']]);
        self::assertSame($expected, max(array_map(mb_strwidth(...), explode("\n", $output->fetch()))));
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function providerTableWidths(): array
    {
        return [
            'narrow terminal keeps 24 columns' => ['40', 'A', 50],
            'terminal width' => ['90', 'A', 90],
            'terminal width with wide ID' => ['90', 'ÄÖÜÄÖÜÄÖÜÄ', 90],
            'wide terminal keeps 72 columns' => ['200', 'A', 98],
        ];
    }

    public function testTableCapsTheIdResultAndTestColumns(): void
    {
        $output = new BufferedOutput();
        (new SpecificationTable())->table($output, ['ID', 'Statement', 'Result', 'Tests'], [['ABCDEFGHIJKLMNOPQRSTUVWXYZ1234', 'Short.', 'unsupported-and-more', '1234567890/1234567890']]);
        self::assertSame(<<<'TEXT'
+--------------------------+-----------+----------------+--------------+
| ID                       | Statement | Result         | Tests        |
+--------------------------+-----------+----------------+--------------+
| ABCDEFGHIJKLMNOPQRSTUVWX | Short.    | unsupported-an | 1234567890/1 |
| YZ1234                   |           | d-more         | 234567890    |
+--------------------------+-----------+----------------+--------------+

TEXT, $output->fetch());
    }

    public function testTableSizesColumnsByTheirHeaders(): void
    {
        putenv('COLUMNS=50');
        $output = new BufferedOutput();
        (new SpecificationTable())->table($output, ['Identifier', 'Statement', 'Outcome', 'Linked'], [['A', str_repeat('y', 30), 'ok', '1']]);
        self::assertSame(<<<'TEXT'
+------------+--------------------------+---------+--------+
| Identifier | Statement                | Outcome | Linked |
+------------+--------------------------+---------+--------+
| A          | yyyyyyyyyyyyyyyyyyyyyyyy | ok      | 1      |
|            | yyyyyy                   |         |        |
+------------+--------------------------+---------+--------+

TEXT, $output->fetch());
    }
}
