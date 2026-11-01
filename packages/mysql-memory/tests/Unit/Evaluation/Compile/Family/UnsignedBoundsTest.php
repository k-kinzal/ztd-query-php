<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Family;

use MySqlMemory\Evaluation\Compile\Family\UnsignedBounds;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnsignedBounds::class)]
#[Small]
final class UnsignedBoundsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerPredicates(): iterable
    {
        yield 'unsigned negative' => ['ID=-1', 0];
        yield 'reversed negative' => ['-1=ID', 0];
        yield 'null-safe negative' => ['ID<=>-1', 0];
        yield 'negative fraction' => ['ID=-0.1', 0];
        yield 'negative zero' => ['ID=-0', 1];
        yield 'signed field' => ['TIME=-1', 1];
        yield 'string coercion' => ['USER=-1', 1];
        yield 'positive' => ['ID=1', 1];
        yield 'column' => ['ID=TIME', 1];
        yield 'inequality' => ['ID<>-1', 1];
    }

    #[DataProvider('providerPredicates')]
    public function testImpossibleSkipsOnlyKnownUnsignedContradictions(string $predicate, int $reads): void
    {
        $session = (new Instance())->connect();
        $session->run('SELECT COUNT(*) FROM information_schema.processlist WHERE ' . $predicate);

        self::assertSame($reads, $session->instance->registry->status->read('Deprecated_use_i_s_processlist_count'));
    }

    public function testImpossibleDoesNotReplaceNullableScalarComparisons(): void
    {
        $session = (new Instance())->connect();
        $session->run('CREATE DATABASE d; CREATE TABLE d.t(a BIGINT UNSIGNED); INSERT INTO d.t VALUES(NULL), (0)');
        $result = $session->run('SELECT a=-1 FROM d.t')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null], ['0']], $result->rows);
    }
}
