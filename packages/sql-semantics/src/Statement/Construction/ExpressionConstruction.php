<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction;

use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

/**
 * Binds a closed set of new scalar inputs at their actual evaluation site.
 * @visibility SqlSemantics
 */
final class ExpressionConstruction
{
    /**
     * No bound expression or third-party input implementation enters this dispatcher.
     * @throws InvalidConstruction
     */
    public function derive(ScalarInput $input, Scope|SqliteAliasScope $scope): ScalarExpression
    {
        return match (true) {
            $input instanceof E\NullConstant, $input instanceof E\SqliteInteger,
            $input instanceof E\SqliteReal, $input instanceof E\SqliteText,
            $input instanceof E\SqliteBlob, $input instanceof E\SqliteCurrentTime => $input,
            $input instanceof Expression\GroupedInput => new E\Rendering\GroupedExpression($this->derive($input->operand, $scope), $input->before, $input->after),
            $input instanceof Expression\ColumnUse => (new ColumnConstruction())->derive($input, $scope),
            $input instanceof Expression\UnaryInput => new E\SqliteUnary($input->operator, $this->derive($input->operand, $scope)),
            $input instanceof Expression\BinaryInput => new E\SqliteBinary($this->derive($input->left, $scope), $input->operator, $this->derive($input->right, $scope), $input->layout),
            $input instanceof Expression\BetweenInput => new E\SqliteBetween($this->derive($input->subject, $scope), $this->derive($input->lower, $scope), $this->derive($input->upper, $scope), $input->negated),
            $input instanceof Expression\InListInput => new E\SqliteInList($this->derive($input->subject, $scope), $input->negated, ...array_map(fn (ScalarInput $choice): ScalarExpression => $this->derive($choice, $scope), $input->choices)),
            $input instanceof Expression\CastInput => new E\Conversion\SqliteCast($this->derive($input->operand, $scope), $input->target),
            $input instanceof Expression\CollationInput => new E\Conversion\SqliteCollated($this->derive($input->operand, $scope), $input->collation),
            $input instanceof Conditional\SearchedCaseInput => new E\Conditional\SqliteSearchedCase($this->branches($input->branches, $scope)),
            $input instanceof Conditional\SimpleCaseInput => new E\Conditional\SqliteSimpleCase($this->derive($input->base, $scope), $this->branches($input->branches, $scope)),
            $input instanceof Subquery\ScalarQueryInput => new E\Subquery\SqliteScalarSubquery((new SubqueryConstruction())->derive($input->query, $scope)),
            $input instanceof Subquery\ExistsInput => new E\Subquery\SqliteExists((new SubqueryConstruction())->derive($input->query, $scope)),
            $input instanceof Subquery\InQueryInput => new E\Subquery\SqliteInQuery($this->derive($input->subject, $scope), (new SubqueryConstruction())->derive($input->query, $scope), $input->negated),
            default => throw new InvalidConstruction('Only the registered concrete scalar input types can construct an expression.'),
        };
    }

    /**
     * Resolves every CASE arm once while preserving the ordered evaluation structure.
     */
    public function branches(Conditional\CaseBranchesInput $input, Scope|SqliteAliasScope $scope): E\Conditional\SqliteCaseBranches
    {
        return new E\Conditional\SqliteCaseBranches(
            $input->otherwise === null ? null : $this->derive($input->otherwise, $scope),
            ...array_map(fn (Conditional\CaseArmInput $arm): E\Conditional\SqliteCaseArm => new E\Conditional\SqliteCaseArm($this->derive($arm->when, $scope), $this->derive($arm->then, $scope)), $input->arms),
        );
    }
}
