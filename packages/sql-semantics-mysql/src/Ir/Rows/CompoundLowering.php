<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows;

use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Query;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;

/**
 * Builds native set operands from the original bound query scopes.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CompoundLowering
{
    /**
     * Preserves the set tree and lowers each projection with its own facts.
     *
     * @throws InvalidConstruction When a query uses an unsupported arm
     */
    public function lower(BoundStatement $operation, Query $query): Select|Compound
    {
        Check::input(!$query instanceof SetOperation || $query->left instanceof Query, 'A native compound arm cannot carry unrepresented leading clauses.');

        return match (true) {
            $query instanceof ParsedSelect => (new SelectLowering())->lower($operation, $query),
            $query instanceof ParenthesizedQuery => $this->lower($operation, $query->query),
            $query instanceof SetOperation => new Compound(
                $this->lower($operation, $query->left),
                CompoundOperator::from($query->operator->value),
                $query->quantifier === SetQuantifier::All,
                $this->lower($operation, $query->right),
            ),
            default => throw new InvalidConstruction('A native compound requires checked SELECT arms.'),
        };
    }
}
