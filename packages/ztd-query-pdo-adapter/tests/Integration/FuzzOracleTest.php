<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fuzz\Correctness\PhysicalTableSnapshot;
use Fuzz\Correctness\ResultComparator;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class FuzzOracleTest extends TestCase
{
    public function testUnorderedProjectionDoesNotRequireThePrimaryKey(): void
    {
        $comparator = new ResultComparator();
        self::assertTrue($comparator->compareRows([['name' => 'A'], ['name' => 'B']], [['name' => 'B'], ['name' => 'A']], ['id']));
        self::assertFalse($comparator->compareRows([['name' => 'A'], ['name' => 'B']], [['name' => 'B'], ['name' => 'B']], ['id']));
    }

    public function testOrderedResultsKeepTheirOrdering(): void
    {
        self::assertFalse((new ResultComparator())->compareRows([['id' => 1], ['id' => 2]], [['id' => 2], ['id' => 1]], ordered: true));
    }

    public function testUnorderedResultsPreserveDuplicateCounts(): void
    {
        self::assertFalse((new ResultComparator())->compareRows([['id' => 1], ['id' => 1]], [['id' => 1], ['id' => 2]]));
    }

    public function testDecimalNormalizationKeepsIntegerZeros(): void
    {
        $comparator = new ResultComparator();
        self::assertFalse($comparator->compareDecimal('10', '1'));
        self::assertTrue($comparator->compareDecimal('10', '10.00'));
        self::assertTrue($comparator->compareDecimal('1.20', '1.2'));
    }

    public function testPhysicalSnapshotAcceptsAnUnchangedTable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $before = PhysicalTableSnapshot::capture($pdo, 'users');
        PhysicalTableSnapshot::assertUnchanged($pdo, 'users', $before, 'SELECT * FROM users', 7);
        self::assertSame($before, PhysicalTableSnapshot::capture($pdo, 'users'));
    }

}
