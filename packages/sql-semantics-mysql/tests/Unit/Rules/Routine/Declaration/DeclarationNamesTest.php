<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\DeclarationNames;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(DeclarationNames::class)]
#[Small]
final class DeclarationNamesTest extends TestCase
{
    public function testNamesChecksIdentityBeforeATypeExists(): void
    {
        $names = [];
        $rules = new DeclarationNames();

        self::assertNull($rules->names([new Name('x')], $names, M\Routine\Problem\ProgramRule::DuplicateVariable));
        self::assertSame('Duplicate variable: X', $rules->names([new Name('X')], $names, M\Routine\Problem\ProgramRule::DuplicateVariable)?->message());
    }

    public function testAddKeepsVariableAndConditionNamespacesIndependent(): void
    {
        $rule = new DeclarationNames();
        $names = [];
        $stage = 0;
        $variable = new M\Routine\Program\VariableDeclaration([new Name('x')], new M\Type\Integral(M\Type\Kind\IntegralKind::Int));
        $condition = new M\Routine\Program\ConditionDeclaration(new Name('X'), new M\Routine\Condition\ErrorCode(new M\Literal\Numeral('1000')));
        self::assertNull($rule->add($variable, $names, $stage));
        self::assertNull($rule->add($condition, $names, $stage));
        self::assertSame(M\Routine\Problem\ProgramRule::DuplicateVariable, $rule->add($variable, $names, $stage)?->rule);
        self::assertSame(M\Routine\Problem\ProgramRule::DuplicateCondition, $rule->add($condition, $names, $stage)?->rule);
    }

    public function testAddRejectsTheSameNameWithinOneVariableDeclaration(): void
    {
        $variable = new M\Routine\Program\VariableDeclaration([new Name('x'), new Name('X')], new M\Type\Integral(M\Type\Kind\IntegralKind::Int));
        $names = [];
        $stage = 0;
        self::assertSame('Duplicate variable: X', (new DeclarationNames())->add($variable, $names, $stage)?->message());
        $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish((new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->context(), new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), new M\Routine\Program\Block([$variable])));
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof M\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame('Duplicate variable: X', $notices[0]->message());

    }

    public function testAddRejectsVariablesAfterHandlersBeforePublishingAProcedure(): void
    {
        $handler = new M\Routine\Program\HandlerDeclaration(M\Routine\Program\HandlerAction::Continue, [new M\Routine\Condition\ErrorCode(new M\Literal\Numeral('1000'))], new M\Routine\Program\Block());
        $variable = new M\Routine\Program\VariableDeclaration([new Name('x')], new M\Type\Integral(M\Type\Kind\IntegralKind::Int));
        $names = [];
        $stage = 0;
        $rule = new DeclarationNames();
        self::assertNull($rule->add($handler, $names, $stage));
        self::assertSame(M\Routine\Problem\ProgramRule::DeclarationAfterCursorOrHandler, $rule->add($variable, $names, $stage)?->rule);
        $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish((new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->context(), new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), new M\Routine\Program\Block([$handler, $variable])));
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof M\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame('Variable or condition declaration after cursor or handler declaration', $notices[0]->message());

    }
}
