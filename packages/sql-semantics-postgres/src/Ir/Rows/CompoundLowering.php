<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetQuantifier;

/**
 * Builds native set operands from the original bound query scopes.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
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
