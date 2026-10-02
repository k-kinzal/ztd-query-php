<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Correspondence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

#[CoversClass(V\ScalarMatch::class)]
#[Small]
final class ScalarMatchTest extends TestCase
{
    #[DataProvider('providerAlteredOperands')]
    public function testCheckRejectsChangedActualOperands(C\ScalarInput $input, E\ScalarExpression $actual): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $this->expectException(InvariantViolation::class);
        (new V\ScalarMatch())->check($input, $actual, $scope);
    }

    /**
     * @return array<string, array{C\ScalarInput, E\ScalarExpression}>
     */
    public static function providerAlteredOperands(): array
    {
        $one = new E\SqliteInteger(new \SqlSemantics\Statement\Literal\UnsignedInteger('1'));
        $two = new E\SqliteInteger(new \SqlSemantics\Statement\Literal\UnsignedInteger('2'));
        return [
            'literal' => [$one, $two],
            'unary operand' => [new C\Expression\UnaryInput(E\SqliteUnaryOperator::Negate, $one), new E\SqliteUnary(E\SqliteUnaryOperator::Negate, $two)],
            'swapped binary operands' => [new C\Expression\BinaryInput($one, E\SqliteBinaryOperator::Subtract, $two), new E\SqliteBinary($two, E\SqliteBinaryOperator::Subtract, $one)],
            'swapped range bounds' => [new C\Expression\BetweenInput($one, $one, $two), new E\SqliteBetween($one, $two, $one)],
            'omitted membership choice' => [new C\Expression\InListInput($one, false, $one, $two), new E\SqliteInList($one, false, $one)],
            'changed membership choice' => [new C\Expression\InListInput($one, false, $one, $two), new E\SqliteInList($one, false, $two, $two)],
            'changed group operand' => [new C\Expression\GroupedInput($one), new E\Rendering\GroupedExpression($two)],
            'changed collation operand' => [new C\Expression\CollationInput($one, new Name('binary')), new E\Conversion\SqliteCollated($two, new Name('binary'))],
            'changed case result' => [new C\Conditional\SearchedCaseInput(new C\Conditional\CaseBranchesInput(null, new E\Rendering\SqliteElseLayout(), new C\Conditional\CaseArmInput($one, $two))), new E\Conditional\SqliteSearchedCase(new E\Conditional\SqliteCaseBranches(null, new E\Rendering\SqliteElseLayout(), new E\Conditional\SqliteCaseArm($one, $one)))],
        ];
    }

    public function testChildrenRetainsTheActualLookupWithoutReplacingItByItsSpelling(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $input = new C\Expression\ColumnUse(new Name('missing'));
        $actual = new E\ColumnReference($scope, $input->name);
        self::assertSame([], (new V\ScalarMatch())->children(new V\ScalarPair($input, $actual, $scope)));
    }
}
