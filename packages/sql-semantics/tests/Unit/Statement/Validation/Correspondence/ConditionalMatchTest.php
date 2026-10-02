<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Correspondence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Validation\Correspondence as V;
use SqlSemantics\Statement\Validation\Failure\InvariantViolation;

#[CoversClass(V\ConditionalMatch::class)]
#[Small]
final class ConditionalMatchTest extends TestCase
{
    public function testChildrenRejectsReplacingSimpleCaseWithSearchedCase(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $value = new E\NullConstant();
        $input = new C\Conditional\SimpleCaseInput($value, new C\Conditional\CaseBranchesInput(null, new C\Conditional\CaseArmInput($value, $value)));
        $actual = new E\Conditional\SqliteSearchedCase(new E\Conditional\SqliteCaseBranches(null, new E\Conditional\SqliteCaseArm($value, $value)));
        $this->expectException(InvariantViolation::class);
        (new V\ConditionalMatch())->children(new V\ScalarPair($input, $actual, $scope));
    }

    public function testBranchesRejectsChangingAnAbsentElseToAnExplicitNull(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $value = new E\NullConstant();
        $input = new C\Conditional\CaseBranchesInput(null, new C\Conditional\CaseArmInput($value, $value));
        $actual = new E\Conditional\SqliteCaseBranches($value, new E\Conditional\SqliteCaseArm($value, $value));
        $this->expectException(InvariantViolation::class);
        (new V\ConditionalMatch())->branches($input, $actual, $scope);
    }

    public function testBranchesKeepsEveryTestAndResultInItsActualPosition(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $test = new E\SqliteText(new \SqlSemantics\Statement\Literal\StringLiteral('test'));
        $result = new E\SqliteText(new \SqlSemantics\Statement\Literal\StringLiteral('result'));
        $fallback = new E\NullConstant();
        $input = new C\Conditional\CaseBranchesInput($fallback, new C\Conditional\CaseArmInput($test, $result));
        $actual = new E\Conditional\SqliteCaseBranches($fallback, new E\Conditional\SqliteCaseArm($test, $result));
        $children = (new V\ConditionalMatch())->branches($input, $actual, $scope);
        self::assertSame([$test, $result, $fallback], array_column($children, 'actual'));
    }
}
