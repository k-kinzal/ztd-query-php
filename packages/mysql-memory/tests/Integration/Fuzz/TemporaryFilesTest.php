<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\TemporaryFiles;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type Observation array{error?: array{int, string, string}, warnings?: list<array{string, string, string}>, results?: list<list<int>>, disconnected?: bool}
 */
#[CoversNothing]
#[Small]
final class TemporaryFilesTest extends TestCase
{
    /**
     * @return iterable<array{Observation, Observation}>
     * @throws JsonException When saved native observations are invalid
     */
    public static function providerIndependentObservations(): iterable
    {
        /** @var list<array{instance: int, sql: string, outcome: Observation}> $saved */
        $saved = json_decode((string) file_get_contents(__DIR__ . '/fixtures/temporary-generation-files.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ([0, 1] as $index) {
            yield [$saved[$index]['outcome'], $saved[$index + 2]['outcome']];
        }
    }

    /**
     * @param Observation $first Complete first native observation
     * @param Observation $second Complete independent native observation
     */
    #[DataProvider('providerIndependentObservations')]
    public function testComparableKeepsTheIndependentPhysicalFileObservations(array $first, array $second): void
    {
        $contract = new TemporaryFiles();
        self::assertNotSame($first, $second);
        self::assertSame($contract->comparable($first, '5.7.44'), $contract->comparable($second, '5.7.44'));
    }

    public function testComparableRequiresAnEmptyMemoryPhysicalValueAndTheObservedRelease(): void
    {
        $contract = new TemporaryFiles();
        $native = ['error' => [1033, 'HY000', "Incorrect information in file: '/tmp/#sql1_3_0.frm'"], 'warnings' => []];
        $memory = ['error' => [1033, 'HY000', "Incorrect information in file: ''"], 'warnings' => []];
        self::assertSame($contract->comparable($native, '5.7.44'), $contract->comparable($memory, '5.7.44'));
        self::assertSame($native, $contract->comparable($native, '8.4.7'));
    }

    /**
     * @return iterable<array{Observation}>
     */
    public static function providerExactObservations(): iterable
    {
        foreach (["Incorrect information in file: './d/t.frm'", "Incorrect information in file: '/tmp/t.frm'", "Incorrect information in file: '/tmp/#sql1_3_0.frm' trailing", "Incorrect information in file: '../d/#sql-1_3.frm'"] as $message) {
            yield [['error' => [1033, 'HY000', $message], 'warnings' => [['Error', '1033', $message]]]];
        }
        $message = "Incorrect information in file: '/tmp/#sql1_3_0.frm'";
        foreach ([[1034, 'HY000', $message], [1033, '42000', $message]] as $error) {
            yield [['error' => $error]];
        }
        yield [['warnings' => [['Warning', '1033', $message], ['Error', '1034', $message]], 'results' => [[1]]]];
    }

    /**
     * @param Observation $observation Fields outside the physical-file contract
     */
    #[DataProvider('providerExactObservations')]
    public function testComparableKeepsCodesStatesLevelsRowsAndLogicalPathsExact(array $observation): void
    {
        self::assertSame($observation, (new TemporaryFiles())->comparable($observation, '5.7.44'));
    }
}
