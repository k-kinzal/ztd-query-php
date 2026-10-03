<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema;

use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

/**
 * Removes a named index definition.
 * @example Reconstructing a conditional removal
 *     $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('example'));
 *     (new \SqlSemantics\Statement\Schema\DropIndex($name, true))->toString() // => 'DROP INDEX IF EXISTS example'
 * @visibility public
 */
final class DropIndex implements Operation
{
    /**
     * Describes the requested operation without applying it to the declaration context.
     */
    public function __construct(public readonly QualifiedName $name, public readonly bool $ifExists = false)
    {
    }

    /**
     * Reconstructs SQL from the operation target and semantic options.
     */
    public function toString(): string
    {
        return 'DROP INDEX ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->name->toString();
    }
}
