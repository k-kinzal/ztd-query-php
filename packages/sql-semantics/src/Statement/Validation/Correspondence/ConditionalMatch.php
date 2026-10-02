<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Correspondence;

use SqlSemantics\Statement\Construction\Conditional as C;
use SqlSemantics\Statement\Construction\Expression as I;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Expression\Conditional as M;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Validation\Check;

/**
 * Matches ordered tests and results without expanding or duplicating evaluation.
 * @visibility SqlSemantics
 */
final class ConditionalMatch
{
    /**
     * Range bounds, list entries, CASE bases, and branch positions are distinct operand roles.
     * @return list<ScalarPair>
     */
    public function children(ScalarPair $pair): array
    {
        $input = $pair->input;
        $actual = $pair->actual;
        $scope = $pair->scope;
        if ($input instanceof I\BetweenInput) {
            Check::invariant($actual instanceof E\SqliteBetween && $input->negated === $actual->negated, 'A range test must retain its operation and polarity.');
            return [new ScalarPair($input->subject, $actual->subject, $scope), new ScalarPair($input->lower, $actual->lower, $scope), new ScalarPair($input->upper, $actual->upper, $scope)];
        }
        if ($input instanceof I\InListInput) {
            Check::invariant($actual instanceof E\SqliteInList && $input->negated === $actual->negated && count($input->choices) === count($actual->choices), 'List membership must retain its operation, polarity, and every choice.');
            $children = [new ScalarPair($input->subject, $actual->subject, $scope)];
            foreach ($input->choices as $index => $choice) {
                $children[] = new ScalarPair($choice, $actual->choices[$index], $scope);
            }
            return $children;
        }
        if ($input instanceof C\SimpleCaseInput) {
            Check::invariant($actual instanceof M\SqliteSimpleCase && SpellingMatch::same($input->layout, $actual->layout), 'A simple CASE must retain its single base evaluation.');
            return [new ScalarPair($input->base, $actual->base, $scope), ...$this->branches($input->branches, $actual->branches, $scope)];
        }
        Check::invariant($input instanceof C\SearchedCaseInput && $actual instanceof M\SqliteSearchedCase && SpellingMatch::same($input->layout, $actual->layout), 'A searched CASE must retain ordered independent truth tests.');
        return $this->branches($input->branches, $actual->branches, $scope);
    }

    /**
     * An absent ELSE is distinct from an explicitly supplied expression.
     * @return list<ScalarPair>
     */
    public function branches(C\CaseBranchesInput $input, M\SqliteCaseBranches $actual, Scope|SqliteAliasScope $scope): array
    {
        Check::invariant(SpellingMatch::same($input->layout, $actual->layout) && count($input->arms) === count($actual->arms), 'Every CASE arm must occupy its requested position.');
        Check::invariant(($input->otherwise === null) === ($actual->otherwise === null), 'CASE must retain whether ELSE was supplied.');
        $children = [];
        foreach ($input->arms as $index => $arm) {
            Check::invariant(SpellingMatch::same($arm->layout, $actual->arms[$index]->layout), 'Each CASE arm must retain its constrained keyword spelling.');
            $children[] = new ScalarPair($arm->when, $actual->arms[$index]->test, $scope);
            $children[] = new ScalarPair($arm->then, $actual->arms[$index]->result, $scope);
        }
        if ($input->otherwise !== null && $actual->otherwise !== null) {
            $children[] = new ScalarPair($input->otherwise, $actual->otherwise, $scope);
        }
        return $children;
    }
}
