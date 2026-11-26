<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\RoutineRules;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Lowering\Form;
use SqlSemantics\Platform\MySql\Model\Lowering\Leaves;
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
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RoutineRules::class)]
#[Medium]
final class RoutineRulesTest extends TestCase
{
    public function testStatementLowersSignalResignalAndGetDiagnostics(): void
    {
        $semantics = new Inspection();
        self::assertInstanceOf(Signal::class, $semantics->inspect("SIGNAL SQLSTATE '45000'")->statement);
        self::assertInstanceOf(Resignal::class, $semantics->inspect('RESIGNAL')->statement);
        self::assertInstanceOf(GetDiagnostics::class, $semantics->inspect('GET DIAGNOSTICS @n = NUMBER')->statement);
        self::assertInstanceOf(AlterRoutine::class, $semantics->inspect('ALTER PROCEDURE p COMMENT "x"')->statement);
        self::assertInstanceOf(DropProgram::class, $semantics->inspect('DROP TRIGGER IF EXISTS tr')->statement);
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
        $semantics = new Inspection('mysql-5.7.44');
        self::assertSame('ALTER FUNCTION db.f NO SQL', $semantics->inspect('alter function db.f no sql')->toString());
        self::assertInstanceOf(AlterEvent::class, $semantics->inspect('ALTER EVENT e ENABLE')->statement);
        self::assertSame('DROP PROCEDURE IF EXISTS p', $semantics->inspect('drop procedure if exists p')->toString());
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
        $semantics = new Inspection();
        self::assertInstanceOf(CreateProcedure::class, $semantics->inspect('CREATE PROCEDURE p() SELECT 1')->statement);
        self::assertInstanceOf(CreateFunction::class, $semantics->inspect('CREATE FUNCTION f() RETURNS INT RETURN 1')->statement);
        self::assertInstanceOf(CreateLoadableFunction::class, $semantics->inspect("CREATE FUNCTION u RETURNS STRING SONAME 'u.so'")->statement);
        self::assertInstanceOf(CreateTrigger::class, $semantics->inspect('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @a = 1')->statement);
        self::assertInstanceOf(CreateEvent::class, $semantics->inspect('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1')->statement);
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
