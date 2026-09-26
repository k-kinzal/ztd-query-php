<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Console\CoverageTable;
use Requirements\Console\Text;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CoversClass(CoverageTable::class)]
#[UsesClass(Text::class)]
#[UsesClass(Fields::class)]
#[Small]
final class CoverageTableTest extends TestCase
{
    public function testRenderShowsOverallSourceAndChangedUnitRows(): void
    {
        $output = new BufferedOutput();
        (new CoverageTable())->render(new SymfonyStyle(new ArrayInput([]), $output), [
            'overall' => ['total' => 3, 'accounted' => 1, 'supported' => 1, 'unsupported' => 0, 'uncovered' => 2, 'percentage' => 33.333333],
            'sources' => ['manual' => ['total' => 3, 'accounted' => 1, 'supported' => 1, 'unsupported' => 0, 'uncovered' => 2, 'percentage' => 66.666666]],
            'diff' => ['total' => 0, 'accounted' => 0, 'supported' => 0, 'unsupported' => 0, 'uncovered' => 0, 'percentage' => null],
        ]);
        $text = preg_replace('/ +$/m', '', $output->fetch());
        self::assertIsString($text);
        self::assertStringStartsWith(<<<'TABLE'
 --------------- ------- ----------- ----------- ------------- ----------- ----------
  Scope           Units   Accounted   Supported   Unsupported   Uncovered   Coverage
 --------------- ------- ----------- ----------- ------------- ----------- ----------
  Overall         3       1           1           0             2           33.33%
  manual          3       1           1           0             2           66.67%
  Changed units   0       0           0           0             0           n/a
 --------------- ------- ----------- ----------- ------------- ----------- ----------

TABLE, $text);
        self::assertStringContainsString('[NOTE] Coverage applies only to the declared source scopes.', $text);
    }

    public function testRenderOmitsChangedUnitsWithoutASnapshotComparison(): void
    {
        $output = new BufferedOutput();
        (new CoverageTable())->render(new SymfonyStyle(new ArrayInput([]), $output), [
            'overall' => ['total' => 4, 'accounted' => 2, 'supported' => 1, 'unsupported' => 1, 'uncovered' => 2, 'percentage' => 50],
            'sources' => ['<info>x</info>' => ['total' => 4, 'accounted' => 2, 'supported' => 1, 'unsupported' => 1, 'uncovered' => 2, 'percentage' => '50'], 'empty' => ['total' => 0, 'accounted' => 0, 'supported' => 0, 'unsupported' => 0, 'uncovered' => 0, 'percentage' => null]],
            'diff' => null,
        ]);
        $text = preg_replace('/ +$/m', '', $output->fetch());
        self::assertIsString($text);
        self::assertStringStartsWith(<<<'TABLE'
 ---------------- ------- ----------- ----------- ------------- ----------- ----------
  Scope            Units   Accounted   Supported   Unsupported   Uncovered   Coverage
 ---------------- ------- ----------- ----------- ------------- ----------- ----------
  Overall          4       2           1           1             2           50.00%
  <info>x</info>   4       2           1           1             2           n/a
  empty            0       0           0           0             0           n/a
 ---------------- ------- ----------- ----------- ------------- ----------- ----------

TABLE, $text);
        self::assertStringNotContainsString('Changed units', $text);
        self::assertStringContainsString('Unsupported units', $text);
    }

    /**
     * @param array<string, mixed> $report
     */
    #[DataProvider('providerRenderRejected')]
    public function testRenderRejectsScopesThatAreNotMappings(array $report, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new CoverageTable())->render(new SymfonyStyle(new ArrayInput([]), new BufferedOutput()), $report);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function providerRenderRejected(): array
    {
        $scope = ['total' => 0, 'accounted' => 0, 'supported' => 0, 'unsupported' => 0, 'uncovered' => 0, 'percentage' => null];
        return [
            'sources list' => [['overall' => $scope, 'sources' => 'manual', 'diff' => null], 'sources must be a mapping.'],
            'overall' => [['overall' => 'all', 'sources' => [], 'diff' => null], 'coverage must be a mapping.'],
            'source' => [['overall' => $scope, 'sources' => ['manual' => 3], 'diff' => null], 'coverage must be a mapping.'],
            'diff' => [['overall' => $scope, 'sources' => [], 'diff' => 'none'], 'coverage must be a mapping.'],
        ];
    }
}
