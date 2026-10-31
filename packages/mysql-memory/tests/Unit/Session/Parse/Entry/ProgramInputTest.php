<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\ProgramInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function testRefusalFindsTheFirstTypedBodyConditionAmongOtherNotices(): void
    {
        $warning = new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::DisplayWidth);
        $first = new RefusedProgramBody(new QualifiedName(new Name('f')), ProgramKind::Function, new ProgramProblem(ProgramRule::MissingReturn, 'f'));
        $second = new RefusedProgramBody(new QualifiedName(new Name('g')), ProgramKind::Function, new ProgramProblem(ProgramRule::MissingReturn, 'g'));
        $input = new ProgramInput();
        self::assertNull($input->refusal([$warning]));
        self::assertSame($first, $input->refusal([$warning, new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($first), new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($second)]));
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function providerTypeCompletions(): iterable
    {
        yield 'parameter' => ['CREATE PROCEDURE p(x INT(11), a INT(256)) SELECT SQL_NO_CACHE 1', [1681, 1681, 1439]];
        yield 'return' => ['CREATE FUNCTION f(x INT(11)) RETURNS INT(256) RETURN 1', [1681, 1681, 1439]];
        yield 'local' => ['CREATE PROCEDURE p() BEGIN DECLARE a INT(256); SELECT SQL_NO_CACHE 1; END', [1681, 1439]];
        yield 'local default' => ['CREATE PROCEDURE p() BEGIN DECLARE a INT(256) DEFAULT (SELECT SQL_NO_CACHE 1); SELECT SQL_CALC_FOUND_ROWS 1; END', [1681, 1681, 1439]];
    }

    /**
     * @param list<int> $codes
     */
    #[DataProvider('providerTypeCompletions')]
    public function testRetainedStopsAtTheCompletedTypeAfterItsPrecedingNotices(string $sql, array $codes): void
    {
        $session = (new Instance('8.4.7', ['sql_mode' => ''], ['d']))->connect(database: 'd');
        $session->run($sql);
        self::assertSame($codes, array_column($session->diagnostics->conditions, 1));
    }

    public function testRetainedInterleavesHeaderNotesAndStopsAtTheBodyCondition(): void
    {
        $session = (new Instance('8.4.7', ['sql_mode' => ''], ['d']))->connect(database: 'd');
        $session->run("CREATE FUNCTION f(a INT(11), b ENUM('x','x')) RETURNS ENUM('y','y') BEGIN SELECT SQL_NO_CACHE 1; FETCH c INTO x; SELECT SQL_CALC_FOUND_ROWS 2; END");
        self::assertSame([1681, 1291, 1291, 1681, 1324], array_column($session->diagnostics->conditions, 1));
        self::assertSame(['Warning', 'Note', 'Note', 'Warning', 'Error'], array_column($session->diagnostics->conditions, 0));
    }

    public function testNotesOrderDirectHeaderConditionsWithoutSourcePositions(): void
    {
        $session = (new Instance('8.4.7', ['sql_mode' => ''], ['d']))->connect(database: 'd');
        $type = new \SqlSemantics\Platform\MySql\Statement\Type\Enumeration(\SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind::Enum, [new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a')]);
        $parameters = new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter(new Name('a'), $type)]);
        $refusal = new RefusedProgramBody(new QualifiedName(new Name('p')), ProgramKind::Procedure, new ProgramProblem(ProgramRule::UndefinedCursor, 'c'), parameters: $parameters);
        $session->text = 'Unrelated input without SQL';
        $error = (new \MySqlMemory\Session\Parse\Reader())->rejection(new \SqlSemantics\Construction\RejectedInput($refusal, 0), $session->text, $session);
        self::assertSame(1324, $error->getCode());
        self::assertTrue($error->recorded);
        self::assertSame([['Note', 1291, "Column '' has duplicated value 'a' in ENUM"], ['Error', 1324, 'Undefined CURSOR: c']], $session->diagnostics->conditions);
        self::assertSame('Unrelated input without SQL', $session->text);
    }

    public function testHeaderStopsAtAStrictEnumerationCondition(): void
    {
        $session = (new Instance('8.4.7', ['sql_mode' => 'STRICT_ALL_TABLES'], ['d']))->connect(database: 'd');
        $type = new \SqlSemantics\Platform\MySql\Statement\Type\Enumeration(\SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind::Enum, [new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a')]);
        $refusal = new RefusedProgramBody(new QualifiedName(new Name('f')), ProgramKind::Function, new ProgramProblem(ProgramRule::MissingReturn, 'f'), returns: $type);
        $notices = (new ProgramInput())->header($refusal, $session);
        self::assertCount(1, $notices);
        self::assertSame(0, $notices[0][0]);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $notices[0][1]);
        self::assertSame(1291, $notices[0][1]->getCode());
    }

    public function testHeaderRequiresADatabaseBeforeCheckingTypes(): void
    {
        $refusal = new RefusedProgramBody(new QualifiedName(new Name('p')), ProgramKind::Procedure, new ProgramProblem(ProgramRule::MissingReturn, 'p'));
        $notices = (new ProgramInput())->header($refusal, (new Instance())->connect());
        self::assertCount(1, $notices);
        self::assertNull($notices[0][0]);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $notices[0][1]);
        self::assertSame(1046, $notices[0][1]->getCode());
    }

    public function testMarkerKeepsTheCompletedHeaderNoteBeforeTheTextProtocolRefusal(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query("SET sql_mode=''");
        $session->run("CREATE FUNCTION f(a ENUM('x','x')) RETURNS INT RETURN ?");
        self::assertSame([['Note', 1291, "Column '' has duplicated value 'x' in ENUM"], ['Error', 1064, "You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near '?' at line 1"]], $session->diagnostics->conditions);
    }

    public function testMarkerNotesRequiresTheHeaderDatabaseBeforeReportingAnyNotes(): void
    {
        $session = (new Instance())->connect();
        $refusal = new RefusedProgramBody(new QualifiedName(new Name('f')), ProgramKind::Function, new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch(), nameEnd: 17);
        $session->text = 'CREATE FUNCTION f() RETURNS INT RETURN ?';
        self::assertSame(1046, (new ProgramInput())->markerNotes($refusal, \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error(), 38, $session)->getCode());
    }

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

    public function testStoppedPreservesAnImmediateBodyRefusal(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $problem = new RefusedProgramBody(new QualifiedName(new Name('p')), ProgramKind::Procedure, new ProgramProblem(ProgramRule::RecursiveCreate, 'FUNCTION'));
        $failure = new \SqlSemantics\Construction\RejectedInput($problem, 40, true);
        $stopped = (new ProgramInput())->stopped($failure, $session);

        self::assertTrue($stopped->aborts);
        self::assertSame(40, $stopped->end);
        self::assertSame($problem, $stopped->problem);
    }

    public function testCompletedEmitsNoHeaderNotesForAnotherDiagnostic(): void
    {
        self::assertSame([], (new ProgramInput())->completed(new ProgramProblem(ProgramRule::MissingReturn, 'f'), (new Instance())->connect()));
    }
}
