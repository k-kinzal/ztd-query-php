<?php

declare(strict_types=1);

namespace Tests\Fuzz;

use Fuzz\Correctness\FailureComparison;
use Fuzz\Correctness\OracleViolation;
use Fuzz\Correctness\ResultComparator;
use Fuzz\Correctness\ScalarComparison;
use mysqli_sql_exception;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that broken outcomes cannot satisfy the fuzz oracles.
 */
#[CoversNothing]
final class OracleTest extends TestCase
{
    /**
     * Integer zeros are significant in decimals.
     */
    public function testIntegerZerosAreSignificantInDecimals(): void
    {
        $comparator = new ScalarComparison();
        self::assertFalse($comparator->compareDecimal('1', '10'));
        self::assertFalse($comparator->compareDecimal('10', '100'));
        self::assertTrue($comparator->compareDecimal('10', '10.00'));
    }

    /**
     * Ordering and multiplicity do not depend on projected primary keys.
     */
    public function testOrderingAndMultiplicityDoNotDependOnProjectedPrimaryKeys(): void
    {
        $comparator = new ResultComparator();
        $rows = [['value' => 'a'], ['value' => 'b'], ['value' => 'b']];
        self::assertFalse($comparator->compareRows($rows, array_reverse($rows), ['id'], [], true));
        self::assertTrue($comparator->compareRows($rows, array_reverse($rows), ['id'], [], false));
        self::assertFalse($comparator->compareRows($rows, [['value' => 'a'], ['value' => 'a'], ['value' => 'b']], ['id'], [], false));
    }

    /**
     * Malformed json is a finding.
     */
    public function testMalformedJsonIsAFinding(): void
    {
        $this->expectException(OracleViolation::class);
        (new ScalarComparison())->compareJson('invalid', 'also invalid');
    }

    /**
     * Different rejections are findings.
     */
    public function testDifferentRejectionsAreFindings(): void
    {
        $this->expectException(OracleViolation::class);
        FailureComparison::verify(new mysqli_sql_exception('duplicate', 1062), new mysqli_sql_exception('missing column', 1054), 'INSERT');
    }
    /**
     * A wrapped duplicate-key rejection is accepted only with matching native evidence.
     */
    public function testMatchingWrappedRejectionsAreAccepted(): void
    {
        $failure = new \ZtdQuery\Adapter\Mysqli\ZtdMysqliException('rejected', 0, new \ZtdQuery\Exception\DuplicateKeyException('INSERT', 'rows', 'PRIMARY', ['id' => 1]));
        FailureComparison::verify(new mysqli_sql_exception('duplicate', 1062), $failure, 'INSERT');
        self::assertSame('unique', FailureComparison::category($failure));
    }
}
