<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Option;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One change to the options of a foreign-data object: `ADD name 'value'`, `SET name 'value'` or `DROP name`.
 *
 * Source: https://www.postgresql.org/docs/17/sql-alterforeigndatawrapper.html.
 *
 * @visibility public
 * @example Dropping an option
 *     $change = new \SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption(\SqlSemantics\Platform\PostgreSql\Statement\Option\OptionAction::Drop, new \SqlSemantics\Statement\Identifier\Name('host'));
 *     $change->value // => null
 * @example Rejecting an added option without a value
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption(\SqlSemantics\Platform\PostgreSql\Statement\Option\OptionAction::Add, new \SqlSemantics\Statement\Identifier\Name('host')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class AlteredOption implements Node
{
    use Snapshot;

    /**
     * @param OptionAction $action What is done with the option
     * @param Name $name The option name
     * @param StringConstant|null $value The new value; absent exactly when the option is dropped
     */
    public function __construct(public readonly OptionAction $action, public readonly Name $name, public readonly ?StringConstant $value = null)
    {
        Check::input(($action === OptionAction::Drop) === ($value === null), 'An option has a value exactly when it is added or set.');
    }

    /**
     * Writes the action, the name and the value.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->action->value)->name($this->name, NameUse::Label)->node($this->value);
    }
}
