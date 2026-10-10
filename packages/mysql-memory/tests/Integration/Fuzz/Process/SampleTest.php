<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz\Process;

use Fuzz\Target\Process\Sample;
use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class SampleTest extends TestCase
{
    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function providerInvalidRows(): iterable
    {
        $row = [1, 'root', 'localhost:1234', 'fz', 'Query', 10, 'executing', 'SELECT 1'];
        yield 'duplicate identity' => [[$row, $row]];
        yield 'non-row' => [[1]];
        yield 'extra field' => [[array_merge($row, [null])]];
        yield 'missing field' => [[array_slice($row, 0, 7)]];
        foreach ([0 => 0, 1 => null, 2 => null, 3 => 1, 4 => null, 5 => '10', 6 => 1, 7 => 1] as $field => $value) {
            yield 'invalid field ' . $field => [[array_replace($row, [$field => $value])]];
        }
    }

    /**
     * @param array<mixed> $rows
     */
    #[DataProvider('providerInvalidRows')]
    public function testIndexedRejectsMalformedOrDuplicateRows(array $rows): void
    {
        self::assertNull(Sample::indexed($rows));
    }

    public function testIndexedPreservesAndSortsEveryField(): void
    {
        $first = [1, 'root', 'localhost:1234', 'fz', 'Query', -10, 'executing', 'SELECT 1'];
        $second = [2, 'event_scheduler', 'localhost', null, 'Daemon', 10, 'Waiting on empty queue', null];

        self::assertSame([1 => $first, 2 => $second], Sample::indexed([$second, $first]));
    }

    public function testReadSamplesIdentityAndThePinnedClockIndependently(): void
    {
        [$target] = Servers::shared();
        $target->repair($target->guard());
        $pdo = $target->connect($target->native, $target->nativeUser, $target->nativePassword);
        $sample = Sample::read($pdo);

        self::assertNotNull($sample);
        self::assertArrayHasKey($sample->current, $sample->rows);
        self::assertLessThanOrEqual(1, abs($sample->rows[$sample->current][5] - $sample->age));
        self::assertGreaterThan(1000000, $sample->age);
    }
}
