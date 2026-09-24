<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Declaration;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * DECLARE names type [DEFAULT expression]: local variables of one domain; without DEFAULT they start as NULL.
 * @visibility public
 * @example Reading declared variables
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE a, b INT DEFAULT 0; END');
 *     $declaration = $statement->body->declarations[0];
 *     $declaration->names // => ['a', 'b']
 *     $declaration->variables()[1]->name // => 'b'
 */
final class VariableDeclaration
{
    /**
     * @var non-empty-list<string>
     */
    public readonly array $names;

    /**
     * Requires distinct nonempty names and a MySQL default expression when one is given.
     * @param list<string> $names
     * @throws InvalidStructure
     */
    public function __construct(array $names, public readonly DeclaredDomain $domain, public readonly ?Expression $default = null)
    {
        Collections::strings($names);
        $folded = array_map(strtolower(...), $names);
        if (in_array('', $names, true) || count(array_unique($folded)) !== count($folded) || ($default !== null && $default->type->dialect !== Dialect::MySql)) {
            throw new InvalidStructure('A variable declaration requires distinct names and a MySQL default expression.');
        }
        $this->names = Collections::nonEmpty($names);
    }

    /**
     * Returns the declared variables in declaration order.
     * @return non-empty-list<LocalVariable>
     */
    public function variables(): array
    {
        return array_map(fn (string $name): LocalVariable => new LocalVariable($name, $this->domain), $this->names);
    }
}
