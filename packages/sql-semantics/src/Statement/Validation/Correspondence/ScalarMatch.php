<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Correspondence;

use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Validation\Check;

/**
 * Walks actual operand slots using an explicit stack, independently of the assembler.
 * @visibility SqlSemantics
 */
final class ScalarMatch
{
    /**
     * No fingerprint, SQL text, token count, or consumed flag establishes this correspondence.
     */
    public function check(C\ScalarInput $input, E\ScalarExpression $actual, Scope|SqliteAliasScope $scope): void
    {
        $pending = [new ScalarPair($input, $actual, $scope)];
        while ($pending !== []) {
            $pair = array_pop($pending);
            array_push($pending, ...$this->children($pair));
        }
    }

    /**
     * The closed dispatch rejects a missing rule instead of accepting an opaque expression.
     * @return list<ScalarPair>
     */
    public function children(ScalarPair $pair): array
    {
        $input = $pair->input;
        if ($input instanceof E\NullConstant || $input instanceof E\SqliteInteger || $input instanceof E\SqliteReal || $input instanceof E\SqliteText || $input instanceof E\SqliteBlob || $input instanceof E\SqliteCurrentTime) {
            (new LiteralMatch())->check($input, $pair->actual);
            return [];
        }
        if ($input instanceof C\Expression\ColumnUse) {
            (new ColumnMatch())->check($input, $pair->actual, $pair->scope);
            return [];
        }
        if ($input instanceof C\Expression\UnaryInput || $input instanceof C\Expression\BinaryInput || $input instanceof C\Expression\GroupedInput || $input instanceof C\Expression\CastInput || $input instanceof C\Expression\CollationInput) {
            return (new OperatorMatch())->children($pair);
        }
        if ($input instanceof C\Expression\BetweenInput || $input instanceof C\Expression\InListInput || $input instanceof C\Conditional\SimpleCaseInput || $input instanceof C\Conditional\SearchedCaseInput) {
            return (new ConditionalMatch())->children($pair);
        }
        Check::invariant($input instanceof C\Subquery\ScalarQueryInput || $input instanceof C\Subquery\ExistsInput || $input instanceof C\Subquery\InQueryInput, 'Every scalar input requires an explicit correspondence rule.');
        return (new SubqueryMatch())->children($pair);
    }
}
