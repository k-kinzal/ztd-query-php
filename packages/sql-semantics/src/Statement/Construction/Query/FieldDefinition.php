<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Query;

/**
 * A new projected expression with an optional explicit SQL alias.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Query\FieldDefinition(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Identifier\Name('answer'));
 *     $input->alias?->value // => 'answer'
 */
final class FieldDefinition
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains explicit new inputs; no existing scope or bound field is accepted.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Construction\ScalarInput $expression, public readonly ?\SqlSemantics\Statement\Identifier\Name $alias = null, public readonly bool $explicitAlias = true)
    {
        \SqlSemantics\Statement\Construction\InputDomain::check($expression);
    }
}
