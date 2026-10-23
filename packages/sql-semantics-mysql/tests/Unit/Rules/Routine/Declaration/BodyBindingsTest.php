<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyBindings;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;

#[CoversClass(BodyBindings::class)]
#[Small]
final class BodyBindingsTest extends TestCase
{
    public function testCheckLeavesOpaqueCodeAndLiteralQuestionMarksUninterpreted(): void
    {
        $rules = new BodyBindings();
        $rules->check(new ExternalBody(new Text('return ?')), ProgramKind::Function);
        $rules->check(new ReturnStatement(new StringLiteral(['?'])), ProgramKind::Function);
        self::assertNull($rules->problem(new ReturnStatement(new StringLiteral(['?'])), ProgramKind::Function));
    }

    public function testProblemInspectsUnreachableNestedInstructions(): void
    {
        $body = new Block(statements: [new IfStatement([new ConditionalBranch(new NumberLiteral('0'), [new ReturnStatement(new Parameter(':bound'))])])]);
        $rules = new BodyBindings();
        self::assertInstanceOf(ParameterScopeMismatch::class, $rules->problem($body, ProgramKind::Function));
        self::assertInstanceOf(ParameterScopeMismatch::class, $rules->problem($body, ProgramKind::Procedure));
        self::assertInstanceOf(ParameterScopeMismatch::class, $rules->problem($body, ProgramKind::Trigger));
    }

    public function testProblemPreservesTheSeparateEventBindingDomain(): void
    {
        self::assertNull((new BodyBindings())->problem(new ReturnStatement(new Parameter()), ProgramKind::Event));
    }
}
