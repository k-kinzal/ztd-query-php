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

#[CoversClass(ProgramText::class)]
#[Small]
final class ProgramTextTest extends TestCase
{
    public function testOfIgnoresUnrelatedSessionTextEvenWithGeneratedInputLocations(): void
    {
        $session = (new Instance())->connect();
        $definition = new \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction(
            new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('f'), new Name('d')),
            new ParameterList([]),
            new Integral(IntegralKind::Int),
            new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new ReturnStatement(new NumberLiteral('7'))),
        );
        $constructed = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context(), $definition, new \SqlSemantics\Statement\Source\SourceMap([new \SqlSemantics\Statement\Source\Origin($definition, 4, 12)]));
        self::assertNotNull($constructed->source());
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

    public function testTriggerRendersItsOriginalQualifiedIdentityWithoutInputText(): void
    {
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('x'), new Name('d'));
        $table = new \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t'), new Name('d')));
        $trigger = new \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger($name, \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime::Before, \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent::Insert, $table, new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block());
        self::assertSame('TRIGGER d.x BEFORE INSERT ON d.t FOR EACH ROW BEGIN END', (new ProgramText(new LanguageProfile(GrammarRelease::MySql5744)))->trigger($trigger));
    }
}
