<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\LegacyProfileResponse;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class LegacyProfileResponseTest extends TestCase
{
    /**
     * @throws JsonException When retained native observations cannot be decoded
     */
    public function testComparableRetainsTwoIndependentNativeResponseVariants(): void
    {
        /** @var array{observations: list<array{instance: int, trial: int, direct: bool, observation: array{results: list<array{columns: list<array<string, int|string>>, rows: list<list<string|null>>}>, warnings: null, disconnected: bool}}> } $saved */
        $saved = json_decode((string) file_get_contents(__DIR__ . '/fixtures/profile-response-5.6.json'), true, 512, JSON_THROW_ON_ERROR);
        $observations = $saved['observations'];
        self::assertCount(16, $observations);
        self::assertSame([0, 1], array_values(array_unique(array_column($observations, 'instance'))));
        self::assertSame([0, true], [$observations[0]['trial'], $observations[0]['direct']]);
        self::assertSame([0, true], [$observations[8]['trial'], $observations[8]['direct']]);
        self::assertNotSame($observations[0]['observation'], $observations[8]['observation']);
        $contract = new LegacyProfileResponse();
        $expected = serialize($observations[0]['observation']);
        self::assertSame(array_fill(0, 16, $expected), array_map(static fn (array $entry): string => serialize($contract->comparable($entry['observation'], '5.6.51', true)), $observations));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, bool}>
     */
    public static function providerUnobserved(): iterable
    {
        $columns = [['name' => 'f()', 'orgname' => 'f()', 'table' => '', 'orgtable' => '', 'db' => '', 'charsetnr' => 63, 'length' => 11, 'type' => 3, 'flags' => 32768, 'decimals' => 0]];
        $observation = ['results' => [['columns' => $columns, 'rows' => array_fill(0, 17, [null])]], 'warnings' => null, 'disconnected' => true];
        yield 'release' => [$observation, '5.7.44', true];
        yield 'text' => [$observation, '5.6.51', false];
        foreach ([[], array_fill(0, 16, [null]), array_fill(0, 18, [null]), array_fill(0, 17, ['1']), array_fill(0, 17, [null, null])] as $index => $rows) {
            $changed = $observation;
            $changed['results'][0]['rows'] = $rows;
            yield 'rows-' . $index => [$changed, '5.6.51', true];
        }
        foreach (['warnings' => [], 'disconnected' => false, 'error' => [2006, 'HY000', 'MySQL server has gone away'], 'warningsError' => [2006, 'HY000', 'MySQL server has gone away']] as $key => $value) {
            yield $key => [array_replace($observation, [$key => $value]), '5.6.51', true];
        }
        foreach (['type', 'charsetnr', 'length', 'flags', 'decimals'] as $field) {
            $changed = $observation;
            $changed['results'][0]['columns'][0][$field] = 999;
            yield $field => [$changed, '5.6.51', true];
        }
        $changed = $observation;
        $changed['results'][0]['columns'][] = $columns[0];
        yield 'second-column' => [$changed, '5.6.51', true];
        $changed = $observation;
        $changed['results'][] = $changed['results'][0];
        yield 'second-result' => [$changed, '5.6.51', true];
    }

    /**
     * @param array<string, mixed> $observation An observation outside the bounded exception
     */
    #[DataProvider('providerUnobserved')]
    public function testComparableKeepsAllOtherObservationsExact(array $observation, string $release, bool $prepared): void
    {
        self::assertSame($observation, (new LegacyProfileResponse())->comparable($observation, $release, $prepared));
    }
}
