<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\LoadAssignments;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(LoadAssignments::class)]
#[Medium]
final class LoadAssignmentsTest extends TestCase
{
    public function testReadRetainsAnEmptySetListAlongsideAnOptionCondition(): void
    {
        $load = (new Semantics(Dialect::MySql, '8.4.7'))->analyze("LOAD XML INFILE 'f' INTO TABLE t COMPRESSION='x'");
        self::assertInstanceOf(M\Dml\Load\LoadTable::class, $load);
        self::assertSame([], $load->assignments);
        self::assertInstanceOf(M\Dml\Problem\LoadConflict::class, $load->diagnostics()[0]);
    }

    public function testAssignmentRetainsEveryAssignmentBeforeTheOptionConflict(): void
    {
        $load = (new Semantics(Dialect::MySql, '8.4.7'))->analyze("LOAD XML INFILE 'f' INTO TABLE d.t COMPRESSION='x' SET a=1,b=DEFAULT,c=ROW(@@missing,2),e=SUM(ROW(1,2))");
        self::assertInstanceOf(M\Dml\Load\LoadTable::class, $load);
        $assignments = $load->assignments;
        self::assertCount(4, $assignments);
        self::assertInstanceOf(M\Dml\Assignment::class, $assignments[0]);
        self::assertInstanceOf(M\Dml\DefaultRequest::class, $assignments[1]->value);
        self::assertInstanceOf(M\Routine\Program\Write\DeferredColumnAssignment::class, $assignments[2]);
        self::assertInstanceOf(M\Expression\Row::class, $assignments[2]->value);
        self::assertInstanceOf(M\Variable\SystemVariable::class, $assignments[2]->value->elements[0]);
        self::assertInstanceOf(M\Routine\Program\Expression\Call\DeferredAggregate::class, $assignments[3]->value);
        self::assertTrue($load->hasExecutionCondition());
    }
}
