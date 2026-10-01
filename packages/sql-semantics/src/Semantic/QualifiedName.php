<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic;

/**
 * A name and its optional namespace.
 * @example Reading semantic relationships
 *     $name = new \SqlSemantics\Semantic\QualifiedName(new \SqlSemantics\Semantic\Name('items'), new \SqlSemantics\Semantic\Name('app'));
 *     $name->toString() // => 'app.items'
 *
 * @visibility public
 */
final class QualifiedName
{
    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly Name $name, public readonly ?Name $schema = null)
    {
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return ($this->schema === null ? '' : $this->schema->toString() . '.') . $this->name->toString();
    }
}
