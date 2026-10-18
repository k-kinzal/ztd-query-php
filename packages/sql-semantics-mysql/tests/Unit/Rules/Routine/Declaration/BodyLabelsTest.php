<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyLabels;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(BodyLabels::class)]
#[Small]
final class BodyLabelsTest extends TestCase
{
    public function testProblemRejectsAnUnboundJumpBeforeOperationConstruction(): void
    {
        $body = new M\Routine\Program\Flow\Leave(new Name('absent'));
        self::assertSame(M\Routine\Problem\ProgramRule::LeaveWithoutLabel, (new BodyLabels())->problem($body)?->rule);
        $this->expectExceptionMessage('LEAVE with no matching label: absent');
        new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), $body);
    }

    public function testProblemDistinguishesBlockAndLoopTargetsAndClosingLabels(): void
    {
        $rules = new BodyLabels();
        self::assertNull($rules->problem(new M\Routine\Program\Block([], [new M\Routine\Program\Flow\Leave(new Name('A'))], new Name('a'))));
        self::assertSame(M\Routine\Problem\ProgramRule::IterateWithoutLabel, $rules->problem(new M\Routine\Program\Block([], [new M\Routine\Program\Flow\Iterate(new Name('a'))], new Name('a')))?->rule);
        self::assertNull($rules->problem(new M\Routine\Program\Flow\Loop([new M\Routine\Program\Flow\Iterate(new Name('a'))], new Name('a'))));
        self::assertSame(M\Routine\Problem\ProgramRule::EndLabelMismatch, $rules->problem(new M\Routine\Program\Block([], [], new Name('a'), new Name('b')))?->rule);
        self::assertSame(M\Routine\Problem\ProgramRule::RedefinedLabel, $rules->problem(new M\Routine\Program\Block([], [new M\Routine\Program\Block([], [], new Name('A'))], new Name('a')))?->rule);
    }

    public function testNestedKeepsSiblingLabelsSeparate(): void
    {
        $body = new M\Routine\Program\Block([], [new M\Routine\Program\Block([], [], new Name('a')), new M\Routine\Program\Block([], [], new Name('a'))]);
        self::assertNull((new BodyLabels())->nested($body, []));
        self::assertSame($body, (new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), $body))->body);
    }
}
