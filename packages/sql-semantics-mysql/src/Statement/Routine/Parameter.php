<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One parameter of a stored procedure or function: its optional direction, its name and its type.
 *
 * Only a procedure parameter has a direction; a parameter without one is an
 * IN parameter.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @visibility public
 * @example Reading a parameter
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p(INOUT total DECIMAL(10, 2)) BEGIN END');
 *     $parameter = $create->statement->parameters->parameters[0];
 *     [$parameter->mode, $parameter->name->value, $parameter->type->name()] // => [\SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode::InOut, 'total', 'DECIMAL']
 */
final class Parameter implements Node
{
    use Snapshot;

    /**
     * @param Name $name The parameter name
     * @param TypeName $type The declared type
     * @param CollationName|null $collation The COLLATE clause of the type
     * @param ParameterMode|null $mode The direction keyword, when written
     */
    public function __construct(public readonly Name $name, public readonly TypeName $type, public readonly ?CollationName $collation = null, public readonly ?ParameterMode $mode = null)
    {
    }

    /**
     * Writes the direction, the name and the type.
     */
    public function render(Output $out): void
    {
        if ($this->mode !== null) {
            $out->keyword($this->mode->value);
        }
        $out->name($this->name, NameUse::Identifier);
        (new ProgramNames())->type($out, $this->type, $this->collation);
    }
}
