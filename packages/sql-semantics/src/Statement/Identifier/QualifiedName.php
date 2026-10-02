<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Identifier;

/**
 * An object's own name and the namespaces qualifying it.
 * @visibility public
 * @example Naming a table in a schema
 *     $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users'), new \SqlSemantics\Statement\Identifier\Name('app'));
 *     $name->toString() // => 'app.users'
 */
final class QualifiedName
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * A catalog qualifier requires a schema qualifier to keep positions unambiguous.
     */
    public function __construct(public readonly Name $name, public readonly ?Name $schema = null, public readonly ?Name $catalog = null)
    {
        \SqlSemantics\Statement\Validation\Check::input($catalog === null || $schema !== null, 'A catalog qualifier requires a schema qualifier.');
    }

    /**
     * Writes qualified identifiers from the outermost namespace to the object.
     */
    public function toString(): string
    {
        return ($this->catalog === null ? '' : $this->catalog->toString() . '.')
            . ($this->schema === null ? '' : $this->schema->toString() . '.')
            . $this->name->toString();
    }
}
