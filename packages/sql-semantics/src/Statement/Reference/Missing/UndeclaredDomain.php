<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Missing;

use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A named data type that is neither fixed by the language profile nor declared in the context.
 *
 * @visibility public
 * @example Naming an undeclared data type
 *     $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('mood'));
 *     (new \SqlSemantics\Statement\Reference\Missing\UndeclaredDomain($name))->describe() // => 'the definition of data type mood'
 */
final class UndeclaredDomain implements MissingInput
{
    use Snapshot;

    /**
     * @param QualifiedName $name The type name as the statement wrote it
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }

    /**
     * Describes the missing definition.
     */
    public function describe(): string
    {
        return 'the definition of data type ' . $this->name->name->value;
    }
}
