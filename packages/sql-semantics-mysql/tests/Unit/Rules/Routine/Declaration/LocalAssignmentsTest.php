<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\LocalAssignments;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(LocalAssignments::class)]
#[Small]
final class LocalAssignmentsTest extends TestCase
{
    public function testScopedDistinguishesWrittenScopeFromAnInheritedSystemDefault(): void
    {
        $local = new M\Utility\Set\NameAssignment(new Name('a'), new M\Literal\NumberLiteral('1'));
        $set = new M\Utility\Set\SetVariables([new M\Utility\Set\NameAssignment(new Name('autocommit'), new M\Literal\NumberLiteral('1'), M\Variable\VariableScope::Global), $local]);
        $rules = new LocalAssignments();
        self::assertNull($rules->scoped($set, [new Name('A')]));
        self::assertNull($rules->scoped($set));
        $parameters = new M\Routine\ParameterList([new M\Routine\Parameter(new Name('a'), new M\Type\Integral(M\Type\Kind\IntegralKind::Int))]);
        self::assertSame($set, (new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), $parameters, $set))->body);
        $set = new M\Utility\Set\SetVariables([new M\Utility\Set\NameAssignment(new Name('a'), new M\Literal\NumberLiteral('1'), M\Variable\VariableScope::Global)]);
        $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish((new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->context(), new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), $parameters, $set));
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof M\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame('A local variable assignment cannot carry a system variable scope.', $notices[0]->message());

    }

    public function testScopedDoesNotLetASiblingBlocksLocalEscape(): void
    {
        $local = new M\Utility\Set\NameAssignment(new Name('a'), new M\Literal\NumberLiteral('1'), M\Variable\VariableScope::Session);
        $set = new M\Utility\Set\SetVariables([$local]);
        $declaration = new M\Routine\Program\VariableDeclaration([new Name('a')], new M\Type\Integral(M\Type\Kind\IntegralKind::Int));
        $rules = new LocalAssignments();
        self::assertSame($local, $rules->scoped(new M\Routine\Program\Block([$declaration], [$set])));
        self::assertNull($rules->scoped(new M\Routine\Program\Block([], [new M\Routine\Program\Block([$declaration], []), $set])));
        self::assertNull($rules->scoped(new M\Utility\Set\SetVariables([new M\Utility\Set\SystemAssignment(new M\Variable\SystemTarget(new Name('a'), M\Variable\VariableScope::Session), new M\Literal\NumberLiteral('1'))]), [new Name('a')]));
    }
}
