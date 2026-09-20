<?php

declare(strict_types=1);

namespace Tests\Fuzz;

use Fuzz\Correctness\FailureComparison;
use Fuzz\Correctness\OracleViolation;
use Fuzz\Correctness\ResultComparator;
use Fuzz\Correctness\SchemaDefinition;
use Fuzz\Correctness\Sqlite\SqliteCorrectnessHarness;
use PDOException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that broken outcomes cannot satisfy the fuzz oracles.
 */
#[CoversNothing]
#[Medium]
final class OracleTest extends TestCase
{
    /**
     * Different failures are findings.
     */
    public function testDifferentFailuresAreFindings(): void
    {
        $native = new PDOException('duplicate');
        $native->errorInfo = ['23505', 7, 'duplicate'];
        $simulated = new PDOException('unknown column');
        $simulated->errorInfo = ['42703', 7, 'unknown column'];
        $this->expectException(OracleViolation::class);
        FailureComparison::verify($native, $simulated, 'INSERT');
    }

    /**
     * Unknown failures are not accepted even when both sides throw.
     */
    public function testUnknownFailuresAreNotAcceptedEvenWhenBothSidesThrow(): void
    {
        $this->expectException(OracleViolation::class);
        FailureComparison::verify(new PDOException('connection lost'), new PDOException('connection lost'), 'SELECT');
    }

    /**
     * Mutation of physical backing is detected.
     */
    public function testMutationOfPhysicalBackingIsDetected(): void
    {
        $harness = new SqliteCorrectnessHarness();
        $harness->setup(new SchemaDefinition('fuzz_rows', 'CREATE TABLE fuzz_rows (id INTEGER PRIMARY KEY, quantity INTEGER, name TEXT)', ['id', 'quantity', 'name'], ['id']), 42);
        $harness->getPhysicalPdo()->exec('DELETE FROM fuzz_rows');
        $this->expectException(OracleViolation::class);
        $this->expectExceptionMessage('physical backing catalog');
        $harness->teardown();
    }

    /**
     * Ordering and duplicate multiplicity are observable.
     */
    public function testOrderingAndDuplicateMultiplicityAreObservable(): void
    {
        $comparator = new ResultComparator();
        $rows = [['value' => 'a'], ['value' => 'b'], ['value' => 'b']];
        self::assertFalse($comparator->compareRows($rows, array_reverse($rows), [], [], true));
        self::assertTrue($comparator->compareRows($rows, array_reverse($rows), [], [], false));
        self::assertFalse($comparator->compareRows($rows, [['value' => 'a'], ['value' => 'a'], ['value' => 'b']], [], [], true));
    }

    /**
     * Malformed json cannot compare equal.
     */
    public function testMalformedJsonCannotCompareEqual(): void
    {
        $this->expectException(OracleViolation::class);
        (new ResultComparator())->compareJson('invalid', 'also invalid');
    }

}
