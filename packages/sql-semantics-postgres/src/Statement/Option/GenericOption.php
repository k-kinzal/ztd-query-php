<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Option;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;

/**
 * One option of an `OPTIONS ( name 'value', ... )` list of a foreign-data object.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createforeigntable.html.
 *
 * @visibility SqlSemantics
 * @example Reading a foreign-data option
 *     $option = new \SqlSemantics\Platform\PostgreSql\Statement\Option\GenericOption(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('host'), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('db1'));
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
