<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * One named option of a definition or storage-parameter list: `name`, `name = value` or `namespace.name = value`.
 *
 * Mirrors PostgreSQL's `DefElem` node as `def_elem` and `reloption_elem`
 * write it. An option without a value is the option given alone, which most
 * commands read as true.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-STORAGE-PARAMETERS.
 *
 * @visibility public
 * @example Reading a storage parameter
 *     $option = new \SqlSemantics\Platform\PostgreSql\Statement\Option\Definition(
 *         new \SqlSemantics\Statement\Identifier\Name('fillfactor'),
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(false, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('70')),
 *     );
 *     [$option->name->value, $option->argument->magnitude->digits] // => ['fillfactor', '70']
 */
final class Definition implements Clause
{
    use Snapshot;

    /**
     * @param Name $name The option name
     * @param OptionArgument|null $argument The value; null when the option is given alone
     * @param Name|null $qualifier The namespace written before the name, such as `toast`
     */
    public function __construct(public readonly Name $name, public readonly ?OptionArgument $argument = null, public readonly ?Name $qualifier = null)
    {
    }

    /**
     * Derives the value, which may be a type name with modifier expressions.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->argument?->deriveClause($derivation, $environment);
    }

    /**
     * Writes the qualified name and the value.
     */
    public function render(Output $out): void
    {
        if ($this->qualifier !== null) {
            $out->name($this->qualifier, NameUse::Label)->symbol('.');
        }
        $out->name($this->name, NameUse::Label);
        if ($this->argument !== null) {
            $out->symbol('=')->node($this->argument);
        }
    }
}
