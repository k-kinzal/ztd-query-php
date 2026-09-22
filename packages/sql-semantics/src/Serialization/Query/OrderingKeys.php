<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Query;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\Ordering;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes output references without substituting or re-evaluating their expressions.
 * @visibility SqlSemantics
 */
final class OrderingKeys
{
    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function write(Expression|Ordering\OutputPosition|Ordering\OutputAlias|Ordering\UnresolvedOutputPosition $key): Tree
    {
        return match (true) {
            $key instanceof Expression => Expressions::write($key),
            $key instanceof Ordering\OutputPosition => Build::keyword((string) ($key->output->ordinal + 1)),
            $key instanceof Ordering\UnresolvedOutputPosition => Build::keyword($key->position->spelling),
            $key instanceof Ordering\OutputAlias => Build::identifier([$key->output->name ?? throw new \SqlSemantics\Model\Validation\InvalidStructure('An output alias requires its name.')], $key->output->expression->type->dialect),
        };
    }
}
