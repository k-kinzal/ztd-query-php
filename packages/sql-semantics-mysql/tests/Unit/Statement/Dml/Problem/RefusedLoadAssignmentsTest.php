<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadAssignments;

#[CoversClass(RefusedLoadAssignments::class)]
#[Medium]
final class RefusedLoadAssignmentsTest extends TestCase
{
    public function testMessageRetainsAFixedRowVariableOutsideTheStatementDomain(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze("LOAD DATA LOCAL INFILE 'f' INTO TABLE t FIELDS TERMINATED BY '' (@v)");
            self::fail('A fixed-row input variable must retain its complete refused request.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(RefusedLoadAssignments::class, $failure->problem);
            self::assertSame('Fixed-row LOAD DATA requires column destinations with defined widths.', $failure->problem->message());
            self::assertNotInstanceOf(\SqlSemantics\Statement\Statement::class, $failure->problem);
            self::assertSame('v', $failure->problem->columns[0]->name->value);
        }
    }

    public function testMessageDescribesTheRetainedAssignmentCondition(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze("LOAD DATA INFILE 'f' INTO TABLE t SET a=ROW(1,2)");
            self::fail('A non-scalar assignment must retain its LOAD request.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(RefusedLoadAssignments::class, $failure->problem);
            self::assertSame('Operand should contain 1 column(s), not 2.', $failure->problem->message());
        }
    }

    public function testRenderRetainsEveryHeaderAndAssignmentRole(): void
    {
        $semantics = new Semantics(Dialect::MySql, '8.4.7');
        try {
            $semantics->analyze("LOAD XML LOW_PRIORITY LOCAL INFILE 'f' REPLACE INTO TABLE d.t PARTITION(p) CHARACTER SET utf8mb4 ROWS IDENTIFIED BY '<r>' FIELDS TERMINATED BY ',' LINES STARTING BY 'x' IGNORE 2 ROWS (a,@b) SET a=DEFAULT,b=ROW(@@missing,2)");
            self::fail('A non-scalar SET operand must retain its complete LOAD request.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(RefusedLoadAssignments::class, $failure->problem);
            $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\MySql\Rendering\Codec($semantics->profile()->grammar));
            $failure->problem->render($out);
            self::assertSame("LOAD XML LOW_PRIORITY LOCAL INFILE 'f' REPLACE INTO TABLE d.t PARTITION (p) CHARSET utf8mb4 ROWS IDENTIFIED BY '<r>' COLUMNS TERMINATED BY ',' LINES STARTING BY 'x' IGNORE 2 LINES (a, @b) SET a = DEFAULT, b = ROW(@@missing, 2)", (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
            self::assertSame('Operand should contain 1 column(s), not 2.', $failure->problem->message());
            self::assertNotInstanceOf(\SqlSemantics\Statement\Statement::class, $failure->problem);
        }
    }
}
