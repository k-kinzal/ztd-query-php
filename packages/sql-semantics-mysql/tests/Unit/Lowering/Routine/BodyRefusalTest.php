<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Routine\BodyRefusal;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;

#[CoversClass(BodyRefusal::class)]
#[Medium]
final class BodyRefusalTest extends TestCase
{
    public function testCheckedLetsAStorableDefinitionThrough(): void
    {
        $statement = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('CREATE FUNCTION f(a INT) RETURNS INT BEGIN SET @x = a; RETURN a; END');
        self::assertInstanceOf(CreateFunction::class, $statement);
        self::assertSame([], $statement->diagnostics());
    }

    /**
     * @return iterable<string, array{string, ProgramKind, string, int, bool}>
     */
    public static function providerRefusals(): iterable
    {
        yield 'RETURN in a procedure stops reading at the RETURN' => ['CREATE PROCEDURE p() BEGIN RETURN 1; SET @a = 1; END', ProgramKind::Procedure, 'RETURN is only allowed in a FUNCTION', 35, true];
        yield 'a result set in a function is found after the whole body' => ['CREATE FUNCTION f() RETURNS INT BEGIN SELECT 1; RETURN 1; END', ProgramKind::Function, 'Not allowed to return a result set from a function', 61, false];
        yield 'a missing RETURN is found after the whole body' => ['CREATE FUNCTION db.f() RETURNS INT BEGIN SET @a = 1; END', ProgramKind::Function, 'No RETURN found in FUNCTION f', 56, false];
        yield 'an undefined cursor in a trigger stops reading' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW BEGIN OPEN c; SET @a = 1; END', ProgramKind::Trigger, 'Undefined CURSOR: c', 62, true];
        yield 'a locked table in an altered event body' => ['ALTER EVENT e DO BEGIN LOCK TABLES t READ; END', ProgramKind::Event, 'LOCK is not allowed in stored procedures', 41, true];
    }

    #[DataProvider('providerRefusals')]
    public function testCheckedRefusesTheDefinitionWhereTheServerFindsTheCondition(string $sql, ProgramKind $kind, string $message, int $end, bool $aborts): void
    {
        try {
            (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze($sql);
            self::fail('The body has a definition condition.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(RefusedProgramBody::class, $failure->problem);
            self::assertSame($kind, $failure->problem->kind);
            self::assertSame($message, $failure->problem->message());
            self::assertSame($end, $failure->end);
            self::assertSame($aborts, $failure->aborts);
        }
    }

    public function testCheckedCarriesTheTypedHeaderOfARoutine(): void
    {
        try {
            (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('CREATE DEFINER = u@h FUNCTION f(a INT, b CHAR(2)) RETURNS DECIMAL(5, 2) BEGIN END');
            self::fail('The function body has no RETURN.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(RefusedProgramBody::class, $failure->problem);
            self::assertNotNull($failure->problem->definer);
            self::assertNotNull($failure->problem->parameters);
            self::assertCount(2, $failure->problem->parameters->parameters);
            self::assertNotNull($failure->problem->returns);
            self::assertSame(3, count($failure->problem->typeEnds));
            self::assertSame(31, $failure->problem->nameEnd);
        }
    }
}
