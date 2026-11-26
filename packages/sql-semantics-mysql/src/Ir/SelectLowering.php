<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\Ordering\ProjectionColumns;
use SqlSemantics\Platform\MySql\Ir\Ordering\ResultLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;

/**
 * Resolves the supported SELECT and constructs its complete value.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SelectLowering
{
    /**
     * Converts the checked operation into a complete value.
     * @throws InvalidConstruction When the operation cannot be represented in this domain
     */
    public function lower(BoundStatement $operation, ?ParsedSelect $operand = null): Select
    {
        $profile = $operation->context->profile;
        Check::input($profile->parameters === ParameterStyle::Native, 'The projection requires the native language profile.');
        $mode = $this->mode($profile->lexical);
        $statement = $operand ?? $operation->statement;
        Check::input($statement instanceof ParsedSelect, 'Complete MySQL IR currently supports a single projection SELECT.');
        Check::input(
            $statement->groupBy === null && $statement->having === null
                && $statement->windows === [] && $statement->qualify === null
                && $statement->procedure === null && $statement->locking === []
                && $statement->into === null && $statement->late === null
                && ($statement->options === [] || $statement->options === [SelectOption::All] || $statement->options === [SelectOption::Distinct]),
            'Complete MySQL IR currently supports projections with positional result selection.',
        );
        $binding = JoinBinding::bind($operation, $statement->from instanceof Dual ? null : $statement->from);
        $where = (new PredicateLowering())->lower($statement->where, $binding);
        $results = (new ProjectionColumns())->lower($operation, $statement, $binding);

        $result = new ResultLowering();
        $order = [];
        foreach ($statement->orderBy as $key) {
            $order[] = $result->order($key);
        }

        return new Select(
            $results,
            $binding->source(),
            $profile->grammar,
            $where,
            $statement->options === [SelectOption::Distinct],
            $order,
            $result->limit($statement->limit),
            $mode,
        );
    }

    /**
     * Selects the closed projection mode shared by the enclosing query and its members.
     * @throws InvalidConstruction When the lexical mode has no complete projection representation
     */
    public function mode(LexicalSettings $lexical): ProjectionMode
    {
        return match (true) {
            $lexical->equals(new LexicalSettings()) => ProjectionMode::Empty,
            $lexical->equals(new LexicalSettings('ANSI_QUOTES')) => ProjectionMode::AnsiQuotes,
            $lexical->equals(new LexicalSettings('NO_BACKSLASH_ESCAPES')) => ProjectionMode::NoBackslashEscapes,
            $lexical->equals(new LexicalSettings('ANSI_QUOTES,NO_BACKSLASH_ESCAPES')) => ProjectionMode::AnsiQuotesAndNoBackslashEscapes,
            default => throw new InvalidConstruction('A complete projection supports ANSI_QUOTES and NO_BACKSLASH_ESCAPES lexical modes.'),
        };
    }
}
