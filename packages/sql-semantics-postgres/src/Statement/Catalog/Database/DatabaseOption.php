<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * One option of CREATE DATABASE or ALTER DATABASE: a name and a value.
 *
 * Mirrors the `DefElem` of `createdb_opt_item`. The name is either one of the
 * keyword spellings or an identifier; the value is a number, a word, a string,
 * TRUE, FALSE, ON or DEFAULT. The optional `=` between them is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-createdatabase.html.
 *
 * @visibility public
 * @example Reading an option written as an identifier
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d is_template = true');
 *     $operation->statement->options[0]->option() // => 'is_template'
 */
final class DatabaseOption implements Clause
{
    use Snapshot;

    /**
     * @param DatabaseOptionKeyword|Name $name The keyword spelling or the identifier that names the option
     * @param OptionArgument $value The value
     */
    public function __construct(public readonly DatabaseOptionKeyword|Name $name, public readonly OptionArgument $value)
    {
    }

    /**
     * Answers the option name the server receives.
     */
    public function option(): string
    {
        return $this->name instanceof Name ? $this->name->value : $this->name->option();
    }

    /**
     * Derives the value.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->value->deriveClause($derivation, $environment);
    }

    /**
     * Writes the name, an equals sign and the value.
     */
    public function render(Output $out): void
    {
        if ($this->name instanceof Name) {
            $out->name($this->name, NameUse::Column);
        } else {
            $out->keyword(...explode(' ', $this->name->value));
        }
        $out->symbol('=')->node($this->value);
    }
}
