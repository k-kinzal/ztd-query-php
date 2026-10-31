<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyStructure;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(BodyStructure::class)]
#[Small]
final class BodyStructureTest extends TestCase
{
    public function testProblemAcceptsTheReturnInAnUnreachableBranch(): void
    {
        $body = new IfStatement([new ConditionalBranch(new NumberLiteral('0'), [new ReturnStatement(new NumberLiteral('1'))])]);
        self::assertNull((new BodyStructure())->problem($body, ProgramKind::Function, new QualifiedName(new Name('f'))));
    }

    public function testProblemRefusesAnUnreachableCommitBeforeTheMissingReturn(): void
    {
        $body = new IfStatement([new ConditionalBranch(new NumberLiteral('0'), [new Commit()])]);
        self::assertSame(ProgramRule::CommitInFunction, (new BodyStructure())->problem($body, ProgramKind::Function, new QualifiedName(new Name('f')))?->rule);
    }

    public function testProblemRejectsAHandlerStatementInAnUnreachableBranch(): void
    {
        $body = new IfStatement([new ConditionalBranch(new NumberLiteral('0'), [new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerClose(new Name('t'))])]);
        self::assertSame('HANDLER is not allowed in stored procedures', (new BodyStructure())->problem($body, ProgramKind::Procedure, new QualifiedName(new Name('p')))?->message());
    }

    public function testLocatedNamesTheInstructionOfAnImmediateRestrictionAndTheBodyOfABodyWideOne(): void
    {
        $return = new ReturnStatement(new NumberLiteral('1'));
        $body = new Block([], [new Commit(), $return]);
        $procedure = (new BodyStructure())->located($body, ProgramKind::Procedure);
        self::assertNotNull($procedure);
        self::assertSame(ProgramRule::ReturnOutsideFunction, $procedure[0]->rule);
        self::assertSame($return, $procedure[1]);
        $function = (new BodyStructure())->located($body, ProgramKind::Function);
        self::assertNotNull($function);
        self::assertSame(ProgramRule::CommitInFunction, $function[0]->rule);
        self::assertSame($body, $function[1]);
        $missing = (new BodyStructure())->located(new Block(), ProgramKind::Function, new QualifiedName(new Name('f')));
        self::assertNotNull($missing);
        self::assertSame('No RETURN found in FUNCTION f', $missing[0]->message());
        self::assertNull((new BodyStructure())->located(new Block([], [$return]), ProgramKind::Function));
    }

    public function testContainedDependsOnTheDeclarationKind(): void
    {
        $rules = new BodyStructure();
        self::assertNull($rules->contained(new Commit(), ProgramKind::Procedure));
        self::assertSame(ProgramRule::CommitInFunction, $rules->contained(new Commit(), ProgramKind::Trigger)?->rule);
        self::assertNull($rules->contained(new Block(), ProgramKind::Trigger));
    }

    public function testPriorityPlacesDynamicSqlBeforeResultSetsAndTransactionBoundaries(): void
    {
        $rules = new BodyStructure();
        self::assertSame([0, 1, 3, 4, 5, 6], array_map($rules->priority(...), [new ProgramProblem(ProgramRule::FunctionStatement, 'Dynamic SQL'), new ProgramProblem(ProgramRule::ResultSet, 'function'), new ProgramProblem(ProgramRule::CommitInFunction), new ProgramProblem(ProgramRule::FunctionStatement, 'RESET'), new ProgramProblem(ProgramRule::FunctionStatement, 'FLUSH'), new ProgramProblem(ProgramRule::MissingReturn, 'f')]));
    }
}
