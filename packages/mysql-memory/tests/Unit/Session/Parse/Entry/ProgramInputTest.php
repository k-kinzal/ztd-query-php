<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\ProgramInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ProgramInput::class)]
#[Small]
final class ProgramInputTest extends TestCase
{
    public function testErrorUsesTheDatabaseAndTypedHeaderWithoutInputText(): void
    {
        $refusal = new RefusedProgramBody(new QualifiedName(new Name('f')), ProgramKind::Function, new ProgramProblem(ProgramRule::MissingReturn, 'f'));
        $session = (new Instance('8.4.7'))->connect();
        $session->text = 'unrelated invalid input';
        self::assertSame(1046, (new ProgramInput())->error($refusal, $session)->getCode());
        $session->variables->database = 'd';
        self::assertSame('No RETURN found in FUNCTION d.f', (new ProgramInput())->error($refusal, $session)->getMessage());
        self::assertSame('unrelated invalid input', $session->text);
    }

    public function testNotesKeepTheTypedHeaderCompletionPositions(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query("SET sql_mode=''");
        $returns = new \SqlSemantics\Platform\MySql\Statement\Type\Enumeration(\SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind::Enum, [new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a')]);
        $refusal = new RefusedProgramBody(new QualifiedName(new Name('f')), ProgramKind::Function, new ProgramProblem(ProgramRule::MissingReturn, 'f'), returns: $returns, typeEnds: [42]);
        $notes = (new ProgramInput())->notes($refusal, $session);
        self::assertCount(1, $notes);
        self::assertSame(42, $notes[0][0]);
        self::assertSame(1291, $notes[0][1]->code->number());
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testBoundaryStopsAtTheNameWhenThereIsNoDatabase(): void
    {
        $session = (new Instance())->connect();
        $refusal = new RefusedProgramBody(new QualifiedName(new Name('f')), ProgramKind::Function, new ProgramProblem(ProgramRule::MissingReturn, 'f'), nameEnd: 17);
        self::assertSame(17, (new ProgramInput())->boundary($refusal, $session, 80));
        $session->variables->database = 'd';
        self::assertSame(80, (new ProgramInput())->boundary($refusal, $session, 80));
    }

    public function testStoppedPreservesAnotherRefusalUnchanged(): void
    {
        $failure = new \SqlSemantics\Construction\RejectedInput(new ProgramProblem(ProgramRule::DuplicateParameter, 'x'), 10);
        self::assertSame($failure, (new ProgramInput())->stopped($failure, (new Instance())->connect()));
    }

    public function testCompletedEmitsNoHeaderNotesForAnotherDiagnostic(): void
    {
        self::assertSame([], (new ProgramInput())->completed(new ProgramProblem(ProgramRule::MissingReturn, 'f'), (new Instance())->connect()));
    }
}
