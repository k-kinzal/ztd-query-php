<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Expression;

/**
 * A name to resolve at a new expression site, without an old declaration pointer.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Expression\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('id'));
 *     $input->name->value // => 'id'
 */
final class ColumnUse implements \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains explicit new inputs; resolution belongs to their new enclosing query.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Identifier\Name $name, public readonly ?\SqlSemantics\Statement\Identifier\QualifiedName $qualifier = null)
    {
    }
}
