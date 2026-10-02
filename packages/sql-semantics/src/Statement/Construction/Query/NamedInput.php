<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Query;

/**
 * A request for a fresh named relation occurrence.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Query\NamedInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('items')), new \SqlSemantics\Statement\Identifier\Name('i'));
 *     $input->alias?->value // => 'i'
 */
final class NamedInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains explicit new inputs; no existing scope or bound field is accepted.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Identifier\QualifiedName $name, public readonly ?\SqlSemantics\Statement\Identifier\Name $alias = null, public readonly bool $explicitAlias = true)
    {
    }
}
