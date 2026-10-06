<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Option;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One option of an `OPTIONS ( name 'value', ... )` list of a foreign-data object.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createforeigntable.html.
 *
 * @visibility public
 * @example Reading a foreign-data option
 *     $option = new \SqlSemantics\Platform\PostgreSql\Statement\Option\GenericOption(new \SqlSemantics\Statement\Identifier\Name('host'), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('db1'));
 *     [$option->name->value, $option->value->value] // => ['host', 'db1']
 */
final class GenericOption implements Node
{
    use Snapshot;

    /**
     * @param Name $name The option name
     * @param StringConstant $value The option value
     */
    public function __construct(public readonly Name $name, public readonly StringConstant $value)
    {
    }

    /**
     * Writes the name and the value.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Label)->node($this->value);
    }
}
