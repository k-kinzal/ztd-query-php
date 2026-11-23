<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetQuantifier;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList;

/**
 * Builds native set operands from the original bound query scopes.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CompoundLowering
{
    /**
     * Preserves the set tree and lowers each projection with its own facts.
     * @param array<int, CastDestination> $inputTypes Native input types inferred in statement order
     *
     * @throws InvalidConstruction When a query uses an unsupported arm
     */
    public function lower(BoundStatement $operation, Query $query, array &$inputTypes = []): Select|ValuesQuery|Compound
    {
        return match (true) {
            $query instanceof ParsedSelect => (new SelectLowering())->lower($operation, $query, $inputTypes),
            $query instanceof ValuesList => (new ValuesLowering())->lower($operation, $query),
            $query instanceof ParenthesizedQuery => $this->lower($operation, $query->query, $inputTypes),
            $query instanceof SetOperation => new Compound(
                $this->lower($operation, $query->left, $inputTypes),
                CompoundOperator::from($query->operator->value),
                $query->quantifier === SetQuantifier::All,
                $this->lower($operation, $query->right, $inputTypes),
            ),
            default => throw new InvalidConstruction('A native compound requires checked SELECT or VALUES arms.'),
        };
    }
}
