<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\ProgramText;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;

#[CoversClass(ProgramText::class)]
#[Small]
final class ProgramTextTest extends TestCase
{
    public function testOfIgnoresUnrelatedSessionTextWithoutInputLocations(): void
    {
        $session = (new Instance())->connect();
        $lowered = $session->analyze('CREATE FUNCTION d.f() RETURNS INT RETURN 7');
        $constructed = new Operation($lowered->context, $lowered->statement);
        $session->text = 'this is not SQL';

        $text = ProgramText::of($constructed, $session);

        self::assertNull($text->source);
        self::assertSame('RETURN 7', $text->routineBody(new ReturnStatement(new NumberLiteral('7'))));
    }

    public function testOfKeepsTheSpellingOfALoweredDefinition(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'CREATE FUNCTION d.f(a INT) RETURNS INT RETURN /* comment */ 7;';
        $operation = $session->analyze($session->text);

        $text = ProgramText::of($operation, $session);

        self::assertNotNull($text->source);
        self::assertSame('RETURN /* comment */ 7', $text->routineBody(new ReturnStatement(new NumberLiteral('7'))));
    }

    public function testParametersRendersTheTypedListWithoutParentheses(): void
    {
        $parameters = new ParameterList([new Parameter(new Name('a'), new Integral(IntegralKind::Int))]);

        self::assertSame('a INT', (new ProgramText(new LanguageProfile(GrammarRelease::MySql847)))->parameters($parameters, true));
    }

    public function testRoutineBodyRendersTheTypedOperand(): void
    {
        self::assertSame('RETURN 7', (new ProgramText(new LanguageProfile(GrammarRelease::MySql847)))->routineBody(new ReturnStatement(new NumberLiteral('7'))));
    }

    public function testBodyRendersWithoutConsultingAProduction(): void
    {
        self::assertSame('RETURN 8', (new ProgramText(new LanguageProfile(GrammarRelease::MySql847)))->body(new ReturnStatement(new NumberLiteral('8')), 'sp_proc_stmt'));
    }

    public function testRenderWritesTheSemanticValue(): void
    {
        self::assertSame('42', (new ProgramText(new LanguageProfile(GrammarRelease::MySql847)))->render(new NumberLiteral('42')));
    }
}
