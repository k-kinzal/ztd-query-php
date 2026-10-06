<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Expression\ProgramOnly;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ParameterPrefix;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Raise;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;

#[CoversClass(ProgramOnly::class)]
#[Medium]
final class ProgramOnlyTest extends TestCase
{
    public function testOutsideProgramReportsARaiseFunction(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $statement = $semantics->analyze('SELECT (SELECT RAISE(IGNORE))')->statement;
        $derivation = new Derivation($semantics->context());
        (new ProgramOnly())->outsideProgram($statement, $derivation);
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertCount(1, $diagnostics);
        self::assertInstanceOf(Misuse::class, $diagnostics[0]);
        self::assertSame(MisuseRule::RaiseOutsideTrigger, $diagnostics[0]->rule);
        self::assertSame('RAISE() may only be used within a trigger-program', $diagnostics[0]->message());
    }

    public function testOutsideProgramReportsNothingWithoutRaise(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $statement = $semantics->analyze('SELECT ?, abs(1)')->statement;
        $derivation = new Derivation($semantics->context());
        (new ProgramOnly())->outsideProgram($statement, $derivation);

        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testOutsideProgramIsAppliedToAQueryAndNotToATriggerProgram(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT RAISE(IGNORE)');
        $trigger = $semantics->analyze("CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT RAISE(ABORT, 'x'); END");

        self::assertCount(1, $query->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $query->facts->diagnostics[0]);
        self::assertSame(MisuseRule::RaiseOutsideTrigger, $query->facts->diagnostics[0]->rule);
        self::assertSame([], $trigger->facts->diagnostics);
    }

    public function testInsideProgramReportsABindParameterOnce(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $derivation = new Derivation($semantics->context());
        (new ProgramOnly())->insideProgram([new IntegerLiteral('1'), new BindParameter(ParameterPrefix::Question), new BindParameter(ParameterPrefix::Colon, 'x')], $derivation);
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertCount(1, $diagnostics);
        self::assertInstanceOf(Misuse::class, $diagnostics[0]);
        self::assertSame(MisuseRule::ParameterInTrigger, $diagnostics[0]->rule);
        self::assertSame('trigger cannot use variables', $diagnostics[0]->message());
    }

    public function testInsideProgramReportsNothingWithoutAParameter(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $derivation = new Derivation($semantics->context());
        (new ProgramOnly())->insideProgram([new IntegerLiteral('1')], $derivation);
        (new ProgramOnly())->insideProgram([], $derivation);

        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testInsideProgramIsAppliedToTheConditionAndTheStatementsOfATrigger(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $body = $semantics->analyze('CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT ?; SELECT ?2; END');
        $condition = $semantics->analyze('CREATE TRIGGER tr AFTER INSERT ON t WHEN ?1 = 1 BEGIN SELECT 1; END');

        self::assertCount(1, $body->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $body->facts->diagnostics[0]);
        self::assertSame(MisuseRule::ParameterInTrigger, $body->facts->diagnostics[0]->rule);
        self::assertCount(1, $condition->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $condition->facts->diagnostics[0]);
        self::assertSame(MisuseRule::ParameterInTrigger, $condition->facts->diagnostics[0]->rule);
    }

    public function testHoldsFindsANodeOfAClassAnywhereInTheStructure(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT (SELECT RAISE(IGNORE)) FROM t WHERE 1 IN (SELECT ?)')->statement;
        $rules = new ProgramOnly();

        self::assertTrue($rules->holds($statement, Raise::class));
        self::assertTrue($rules->holds($statement, BindParameter::class));
        self::assertFalse($rules->holds(new IntegerLiteral('1'), Raise::class));
        self::assertTrue($rules->holds(new BindParameter(ParameterPrefix::At, 'x'), BindParameter::class));
    }
}
