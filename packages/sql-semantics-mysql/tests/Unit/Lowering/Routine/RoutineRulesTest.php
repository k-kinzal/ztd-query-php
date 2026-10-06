<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\RoutineRules;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateLoadableFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Routine\DropProgram;

#[CoversClass(RoutineRules::class)]
#[Medium]
final class RoutineRulesTest extends TestCase
{
    public function testStatementLowersSignalResignalAndGetDiagnostics(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        self::assertInstanceOf(Signal::class, $semantics->analyze("SIGNAL SQLSTATE '45000'")->statement);
        self::assertInstanceOf(Resignal::class, $semantics->analyze('RESIGNAL')->statement);
        self::assertInstanceOf(GetDiagnostics::class, $semantics->analyze('GET DIAGNOSTICS @n = NUMBER')->statement);
        self::assertInstanceOf(AlterRoutine::class, $semantics->analyze('ALTER PROCEDURE p COMMENT "x"')->statement);
        self::assertInstanceOf(DropProgram::class, $semantics->analyze('DROP TRIGGER IF EXISTS tr')->statement);
    }

    public function testStatementRefusesANodeOfNoProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new RoutineRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('The grammar release has no production rule#0.');

        $rules->statement(new Node('rule', 0, []));
    }

    public function testDefinitionRoutesAlterAndDropForms(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        self::assertSame('ALTER FUNCTION db.f NO SQL', $semantics->analyze('alter function db.f no sql')->toString());
        self::assertInstanceOf(AlterEvent::class, $semantics->analyze('ALTER EVENT e ENABLE')->statement);
        self::assertSame('DROP PROCEDURE IF EXISTS p', $semantics->analyze('drop procedure if exists p')->toString());
    }

    public function testDefinitionReportsAnotherForm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new RoutineRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: rule: CREATE x');

        $rules->definition(new Form(new Node('rule', 0, []), 'rule: CREATE x'));
    }

    public function testCreateLowersEveryKindOfStoredProgram(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        self::assertInstanceOf(CreateProcedure::class, $semantics->analyze('CREATE PROCEDURE p() SELECT 1')->statement);
        self::assertInstanceOf(CreateFunction::class, $semantics->analyze('CREATE FUNCTION f() RETURNS INT RETURN 1')->statement);
        self::assertInstanceOf(CreateLoadableFunction::class, $semantics->analyze("CREATE FUNCTION u RETURNS STRING SONAME 'u.so'")->statement);
        self::assertInstanceOf(CreateTrigger::class, $semantics->analyze('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @a = 1')->statement);
        self::assertInstanceOf(CreateEvent::class, $semantics->analyze('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1')->statement);
    }

    public function testCreateRefusesANodeOfNoProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new RoutineRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('The grammar release has no production rule#0.');

        $rules->create(new Node('rule', 0, []), null);
    }
}
