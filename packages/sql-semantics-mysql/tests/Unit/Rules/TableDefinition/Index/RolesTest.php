<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(\SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles::class)]
#[Medium]
final class RolesTest extends TestCase
{
    public function testRequestRetainsNameConditionsWithOrdinaryColumnParts(): void
    {
        $roles = new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles();
        $parts = [new M\Table\Key\ColumnPart(new Name('a'))];
        self::assertTrue($roles->request(new Name('PRIMARY'), $parts));
        self::assertFalse($roles->request(new Name('i'), $parts));
    }

    public function testPartsSeparatesColumnAndRetainedRowKeys(): void
    {
        $roles = new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles();
        $part = new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]));
        self::assertTrue($roles->parts([$part]));
        self::assertFalse($roles->parts([new M\Table\Key\ColumnPart(new Name('a'))]));
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [$part]);
        self::assertTrue($roles->elements([$index]));
        self::assertTrue($roles->commands([new M\Alter\Command\AddConstraint($index)]));
        self::assertFalse($roles->elements([]));
        self::assertFalse($roles->commands([new M\Alter\Command\Force()]));
    }

    public function testPartsRetainsAForbiddenOperandInsideAMultiValuedKey(): void
    {
        $part = new M\Table\Key\ArrayPart(new M\Variable\UserVariable(new Name('x')), new M\Type\CastTarget(M\Type\Kind\CastKind::Unsigned));
        self::assertTrue((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles())->parts([$part]));
        $template = new M\Table\CreateIndex(new Name('k'), new QualifiedName(new Name('t')), [$part]);
        self::assertSame($part, $template->parts[0]);
        $request = new M\Table\CreateIndex(new Name('k'), new QualifiedName(new Name('t')), [$part]);
        self::assertTrue($request->hasExecutionCondition());
    }

    public function testRequestRetainsARowKey(): void
    {
        $request = new M\Table\CreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        self::assertTrue($request->hasExecutionCondition());
    }

    public function testAlterRetainsARetainedRowKey(): void
    {
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        self::assertTrue((new M\Alter\AlterTable(new QualifiedName(new Name('t')), [new M\Alter\Command\AddConstraint($index)]))->hasExecutionCondition());
    }

    public function testCreationRetainsARetainedRowKey(): void
    {
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        self::assertTrue((new M\Table\CreateTable(new QualifiedName(new Name('t')), [$column, $index]))->hasExecutionCondition());
    }
    public function testElementsLeavesOrdinaryColumnKeysInTheDeclarationRole(): void
    {
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [new M\Table\Key\ColumnPart(new Name('a'))]);
        self::assertFalse((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles())->elements([$index]));
    }

    public function testCommandsRecognizesRetainedKeysInAnAddedConstraint(): void
    {
        $part = new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]));
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [$part]);
        self::assertTrue((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles())->commands([new M\Alter\Command\AddConstraint($index)]));
    }

}
