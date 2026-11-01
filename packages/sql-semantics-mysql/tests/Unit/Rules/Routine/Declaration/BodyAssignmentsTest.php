<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyAssignments;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(BodyAssignments::class)]
#[Small]
final class BodyAssignmentsTest extends TestCase
{
    public function testForbiddenDefersAnUnqualifiedNameUntilParametersAreKnown(): void
    {
        $set = new M\Utility\Set\SetVariables([new M\Utility\Set\NameAssignment(new Name('autocommit'), new M\Literal\NumberLiteral('1'))]);
        $body = new M\Routine\Program\Block([], [$set, new M\Routine\Program\Flow\ReturnStatement(new M\Literal\NumberLiteral('1'))]);
        $type = new M\Type\Integral(M\Type\Kind\IntegralKind::Int);
        $parameters = new M\Routine\ParameterList([new M\Routine\Parameter(new Name('AUTOCOMMIT'), $type)]);
        $implementation = new M\Routine\Definition\FunctionImplementation($body);
        self::assertFalse((new BodyAssignments())->forbidden($body));
        self::assertFalse((new BodyAssignments())->forbidden($body, $parameters));
        self::assertTrue((new BodyAssignments())->forbidden($body, new M\Routine\ParameterList([])));
        self::assertSame($body, (new M\Routine\CreateFunction(new QualifiedName(new Name('f')), $parameters, new M\Type\Integral(M\Type\Kind\IntegralKind::Int), $implementation))->body);
        $refusal = (new \SqlSemantics\Platform\MySql\Rules\Routine\Definition\BodyConditions())->refusal($body, M\Routine\ProgramKind::Function, new QualifiedName(new Name('f')), new M\Routine\ParameterList([]));
        self::assertNotNull($refusal);
        self::assertSame('Not allowed to set autocommit from a stored function or trigger', $refusal[0]->message());
    }

    public function testWithinKeepsBlockShadowingLocalToTheBlock(): void
    {
        $set = new M\Utility\Set\SetVariables([new M\Utility\Set\NameAssignment(new Name('autocommit'), new M\Literal\NumberLiteral('1'))]);
        $inner = new M\Routine\Program\Block([new M\Routine\Program\VariableDeclaration([new Name('autocommit')], new M\Type\Integral(M\Type\Kind\IntegralKind::Int))], [$set]);
        self::assertFalse((new BodyAssignments())->within($inner, false));
        self::assertTrue((new BodyAssignments())->within(new M\Routine\Program\Block([], [$inner, $set]), false));
    }

    public function testRestrictedDistinguishesExplicitTargetsFromLocalsAndUserVariables(): void
    {
        $rules = new BodyAssignments();
        $value = new M\Literal\NumberLiteral('1');
        self::assertTrue($rules->restricted(new M\Utility\Set\SystemAssignment(new M\Variable\SystemTarget(new Name('autocommit')), $value), true));
        self::assertFalse($rules->restricted(new M\Utility\Set\UserAssignment(new M\Variable\UserVariable(new Name('autocommit')), $value), false));
        self::assertTrue($rules->restricted(new M\Routine\Program\DeferredAssignment(new Name('autocommit'), new M\Expression\Row([$value, new M\Literal\NumberLiteral('2')])), false));
    }
}
