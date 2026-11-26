<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;

/**
 * One option of CREATE DATABASE or ALTER DATABASE: a name and a value.
 *
 * Mirrors the `DefElem` of `createdb_opt_item`. The name is either one of the
 * keyword spellings or an identifier; the value is a number, a word, a string,
 * TRUE, FALSE, ON or DEFAULT. The optional `=` between them is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-createdatabase.html.
 *
 * @visibility SqlSemantics
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
            $out->name($this->name, NameUse::Identifier);
        } else {
            $out->keyword(...explode(' ', $this->name->value));
        }
        $out->symbol('=')->node($this->value);
    }
}
