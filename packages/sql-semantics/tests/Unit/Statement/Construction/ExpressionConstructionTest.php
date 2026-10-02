<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(C\ExpressionConstruction::class)]
#[Small]
final class ExpressionConstructionTest extends TestCase
{
    public function testDeriveBindsAllOperandsToTheActualUseSite(): void
    {
        $scope = new \SqlSemantics\Statement\Relation\Scope(new Catalog(new SearchPath(new Name('main'))));
        $input = new C\Expression\BinaryInput(new C\Expression\ColumnUse(new Name('left')), E\SqliteBinaryOperator::Add, new C\Expression\ColumnUse(new Name('right')));
        $expression = (new C\ExpressionConstruction())->derive($input, $scope);
        self::assertInstanceOf(E\SqliteBinary::class, $expression);
        self::assertSame($scope, $expression->references()[0]->scope);
        self::assertSame($scope, $expression->references()[1]->scope);
        self::assertNotSame($expression->references()[0], $expression->references()[1]);
    }

    public function testBranchesPreservesOrderedWhenThenAndElseRoles(): void
    {
        $scope = new \SqlSemantics\Statement\Relation\Scope(new Catalog(new SearchPath(new Name('main'))));
        $input = new C\Conditional\CaseBranchesInput(new E\NullConstant(), new E\Rendering\SqliteElseLayout(), new C\Conditional\CaseArmInput(new C\Expression\ColumnUse(new Name('when_value')), new C\Expression\ColumnUse(new Name('then_value'))));
        $branches = (new C\ExpressionConstruction())->branches($input, $scope);
        self::assertSame('WHEN when_value THEN then_value ELSE NULL', $branches->toString());
        self::assertSame('when_value', $branches->arms[0]->test->references()[0]->name->value);
        self::assertSame('then_value', $branches->arms[0]->result->references()[0]->name->value);
    }
}
