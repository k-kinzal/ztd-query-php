<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\GenerationAllocation;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type History list<array{array{error?: array{int, string, string}, affected?: int}, array{warnings: list<array{string, string, string}>, disconnected: bool}, array{results: list<array{columns: list<array<string, int|string>>, rows: list<list<string>>}>, warnings: array{}, disconnected: bool}}>
 */
#[CoversNothing]
#[Small]
final class GenerationAllocationTest extends TestCase
{
    /**
     * @throws JsonException When the retained native observations are malformed
     */
    public function testComparableRetainsTheSameGenerationErrorOnTwoIndependentInstances(): void
    {
        /** @var array{independentObservations: list<array{instance: int, repeat: int, observations: History}>} $saved */
        $saved = json_decode((string) file_get_contents(__DIR__ . '/fixtures/generation-allocation-5.7.json'), true, 512, JSON_THROW_ON_ERROR);
        $observations = $saved['independentObservations'];
        self::assertCount(8, $observations);
        self::assertSame([0, 1], array_values(array_unique(array_column($observations, 'instance'))));
        $contract = new GenerationAllocation();
        $expected = serialize($contract->comparable($observations[0]['observations'], '5.7.44'));
        self::assertSame(array_fill(0, 8, $expected), array_map(static fn (array $entry): string => serialize($contract->comparable($entry['observations'], '5.7.44')), $observations));
        self::assertSame([34, 1111, 1111, 1111, 34, 1111, 1111, 1111], array_map(static fn (array $entry): ?int => $entry['observations'][2][0]['error'][0] ?? null, $observations));
    }

    /**
     * @return iterable<array{int}>
     */
    public static function providerCapacities(): iterable
    {
        foreach ([0, 2, 4096] as $bytes) {
            yield [$bytes];
        }
    }

    #[DataProvider('providerCapacities')]
    public function testComparableKeepsUnobservedErrorsAndAllOtherObservationsExact(int $bytes): void
    {
        $message = 'Invalid use of group function';
        $capacity = 'Memory capacity exceeded (capacity 1 bytes)';
        $history = [[['error' => [1111, 'HY000', $message]], ['warnings' => [['Error', '1111', $message]], 'disconnected' => false], []], [['affected' => 0], ['warnings' => [['Note', '1050', "Table 't' already exists"]], 'disconnected' => false], []], [['error' => [34, 'HY000', $capacity]], ['warnings' => [['Error', '34', $capacity], ['Error', '1111', $message]], 'disconnected' => false], ['counter' => 4]]];
        $contract = new GenerationAllocation();
        self::assertSame([1111, 'HY000', $message], $contract->comparable($history, '5.7.44')[2][0]['error']);
        self::assertSame($history, $contract->comparable($history, '8.4.7'));
        $changed = $history;
        $changed[2][0]['error'][2] = 'Memory capacity exceeded (capacity ' . $bytes . ' bytes)';
        $changed[2][1]['warnings'][0][2] = $changed[2][0]['error'][2];
        self::assertSame($changed, $contract->comparable($changed, '5.7.44'));
        $changed = $history;
        $changed[2][1]['disconnected'] = true;
        self::assertSame($changed, $contract->comparable($changed, '5.7.44'));
        $changed = $history;
        array_pop($changed[2][1]['warnings']);
        self::assertSame($changed, $contract->comparable($changed, '5.7.44'));
        $changed = $history;
        $changed[2][0]['error'][1] = '42000';
        self::assertSame($changed, $contract->comparable($changed, '5.7.44'));
        $changed = $history;
        $changed[2][2]['counter'] = 5;
        self::assertNotSame($contract->comparable($history, '5.7.44'), $contract->comparable($changed, '5.7.44'));
        $changed = $history;
        $changed[1][1]['warnings'][] = ['Warning', '1', 'unrelated'];
        self::assertNotSame($contract->comparable($history, '5.7.44'), $contract->comparable($changed, '5.7.44'));
        self::assertSame(34, $history[2][0]['error'][0]);
    }
}
