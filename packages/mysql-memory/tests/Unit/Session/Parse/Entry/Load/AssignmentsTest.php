<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Load;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\Load\Assignments;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Assignments::class)]
#[Medium]
final class AssignmentsTest extends TestCase
{
    public function testRaiseChecksTheHeaderBeforePreparingTheRetainedValue(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        try {
            $session->semantics()->analyze("LOAD DATA LOCAL INFILE 'f' INTO TABLE absent SET a=ROW(1,2)");
            self::fail('A non-scalar assignment must retain its LOAD input.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(M\Dml\Problem\RefusedLoadAssignments::class, $failure->problem);
            $session->text = 'unrelated invalid input';
            $this->expectException(\MySqlMemory\Error\SqlError::class);
            $this->expectExceptionCode(3948);
            (new Assignments())->raise($failure->problem, $session);
        }
    }

    public function testPrepareResolvesTheDependencyInTheTargetScope(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        try {
            $session->semantics()->analyze("LOAD DATA INFILE 'f' INTO TABLE t SET a=ROW(missing,2)");
            self::fail('A non-scalar assignment must retain its LOAD input.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(M\Dml\Problem\RefusedLoadAssignments::class, $failure->problem);
            $session->text = 'unrelated invalid input';
            $error = (new Assignments())->error($failure->problem, $session);
            self::assertSame(1054, $error->getCode());
            self::assertSame("Unknown column 'missing' in 'field list'", $error->getMessage());
        }
    }

    public function testErrorReusesTheCompleteStructureAcrossDatabaseAndTableStates(): void
    {
        $row = new M\Expression\Row([new M\Name\ColumnUse(new Name('a')), new M\Literal\NumberLiteral('2')]);
        $assignment = new M\Routine\Program\Write\DeferredColumnAssignment(new M\Name\ColumnUse(new Name('a')), $row);
        $load = new M\Dml\Problem\RefusedLoadAssignments(M\Dml\Load\LoadFormat::Data, null, new M\Dml\Load\LoadInput(false, M\Dml\Load\LoadSource::Infile, new M\Literal\Text('f')), null, new M\Dml\WriteTarget(new QualifiedName(new Name('t'))), assignments: [$assignment]);
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->text = 'unrelated invalid input';
        self::assertSame(1046, (new Assignments())->error($load, $session)->getCode());
        $session->variables->database = 'd';
        self::assertSame(1146, (new Assignments())->error($load, $session)->getCode());
        $session->query('CREATE TABLE t(a INT)');
        $session->text = 'unrelated invalid input';
        self::assertSame(1241, (new Assignments())->error($load, $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
    }
}
