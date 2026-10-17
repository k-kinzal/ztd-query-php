<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Audit\Process;

use Fuzz\Structure\Audit\Process\Listing;
use Fuzz\Target\Process\Sample;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Keeps all fields outside the bounded process contract in the comparison.
 */
#[CoversNothing]
#[Small]
final class ListingTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function providerObservations(): iterable
    {
        $base = ['results' => [['columns' => array_map(static fn (string $name): array => ['name' => $name, 'type' => 253], ['Id', 'User', 'Host', 'db', 'Command', 'Time', 'State', 'Info']), 'rows' => [['7', 'root', 'example', null, 'Query', '12', 'init', 'SHOW PROCESSLIST']]]], 'warnings' => [], 'disconnected' => false];
        yield 'valid direct listing' => [$base, true];
        foreach ([0 => '8', 1 => 'other', 2 => 'example:3306', 3 => 'other', 4 => 'Sleep', 5 => '99', 6 => 'other', 7 => 'unrelated'] as $index => $value) {
            $changed = $base;
            $changed['results'][0]['rows'][0][$index] = $value;
            yield 'changed field ' . $index => [$changed, false];
        }
        $changed = $base;
        $changed['results'][0]['rows'][] = $base['results'][0]['rows'][0];
        yield 'duplicate identity' => [$changed, false];
        $changed = $base;
        $changed['results'][0]['columns'][0]['type'] = 8;
        yield 'metadata' => [$changed, false];
        $changed = $base;
        $changed['warnings'] = [['Warning', '1', 'unexpected']];
        yield 'warnings' => [$changed, false];
        $changed = $base;
        $changed['disconnected'] = true;
        yield 'disconnected' => [$changed, false];
    }

    /**
     * @param array<string, mixed> $memory
     */
    #[DataProvider('providerObservations')]
    public function testValidatedKeepsEveryUncontractedFieldExact(array $memory, bool $matches): void
    {
        $nativeRow = [19, 'root', 'example:43210', null, 'Query', 12, 'init', 'SHOW PROCESSLIST'];
        $memoryRow = [7, 'root', 'example', null, 'Query', 12, 'init', 'SHOW PROCESSLIST'];
        $native = ['results' => [['columns' => array_map(static fn (string $name): array => ['name' => $name, 'type' => 253], ['Id', 'User', 'Host', 'db', 'Command', 'Time', 'State', 'Info']), 'rows' => [array_map(static fn (int|string|null $value): ?string => $value === null ? null : (string) $value, $nativeRow)]]], 'warnings' => [], 'disconnected' => false];
        $left = Listing::validated($native, new Sample(19, 11, 'OFF', [19 => $nativeRow]), new Sample(19, 13, 'OFF', [19 => $nativeRow]), false);
        $right = Listing::validated($memory, new Sample(7, 11, 'OFF', [7 => $memoryRow]), new Sample(7, 13, 'OFF', [7 => $memoryRow]), true);

        self::assertSame($matches, $left === $right);
        self::assertSame([Listing::CONTRACT], $left['contracts'] ?? null);
    }
}
