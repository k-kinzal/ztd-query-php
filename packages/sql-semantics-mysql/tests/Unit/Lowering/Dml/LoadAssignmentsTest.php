<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\LoadAssignments;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(LoadAssignments::class)]
#[Medium]
final class LoadAssignmentsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, class-string<\SqlSemantics\Statement\Fact\Diagnostic>}>
     */
    public static function providerUrlInputs(): iterable
    {
        foreach (['8.0.44', '8.1.0', '8.2.0', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, in_array($release, ['8.0.44', '8.1.0'], true) ? M\Expression\Problem\RefusedScalar::class : M\Dml\Problem\RefusedLoadOptions::class];
        }
    }

    /**
     * @param class-string<\SqlSemantics\Statement\Fact\Diagnostic> $problem The first release-specific refusal role
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUrlInputs')]
    public function testOrdinaryKeepsTheReleaseSpecificOrderOfUrlAndRowConditions(string $release, string $problem): void
    {
        try {
            (new Semantics(Dialect::MySql, $release))->analyze("LOAD DATA URL 'f' INTO TABLE t SET a=ROW(1,2)");
            self::fail('The LOAD input must retain its typed refusal.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf($problem, $failure->problem);
        }
    }

    public function testReadRetainsAnEmptySetListWithoutInventingAnOperand(): void
    {
        try {
            (new Semantics(Dialect::MySql, '8.4.7'))->analyze("LOAD XML INFILE 'f' INTO TABLE t COMPRESSION='x'");
            self::fail('The LOAD input must retain its typed refusal.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(M\Dml\Problem\RefusedLoadOptions::class, $failure->problem);
            self::assertSame([], $failure->problem->assignments);
        }
    }

    public function testAssignmentRetainsEveryAssignmentBeforeTheOptionConflict(): void
    {
        try {
            (new Semantics(Dialect::MySql, '8.4.7'))->analyze("LOAD XML INFILE 'f' INTO TABLE d.t COMPRESSION='x' SET a=1,b=DEFAULT,c=ROW(@@missing,2),e=SUM(ROW(1,2))");
            self::fail('The incompatible LOAD input must retain its typed refusal.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(M\Dml\Problem\RefusedLoadOptions::class, $failure->problem);
            $assignments = $failure->problem->assignments;
            self::assertCount(4, $assignments);
            self::assertInstanceOf(M\Dml\Assignment::class, $assignments[0]);
            self::assertInstanceOf(M\Dml\DefaultRequest::class, $assignments[1]->value);
            self::assertInstanceOf(M\Routine\Program\Write\DeferredColumnAssignment::class, $assignments[2]);
            self::assertInstanceOf(M\Expression\Row::class, $assignments[2]->value);
            self::assertInstanceOf(M\Variable\SystemVariable::class, $assignments[2]->value->elements[0]);
            self::assertInstanceOf(M\Routine\Program\Expression\Call\DeferredAggregate::class, $assignments[3]->value);
        }
    }
}
