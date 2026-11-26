<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * A HANDLER or VALIDATOR clause naming a support function, or NO HANDLER or NO VALIDATOR.
 *
 * Mirrors the `DefElem` `handler` or `validator` whose argument is the
 * function name, or nothing for the NO form. In ALTER FOREIGN DATA WRAPPER the
 * NO form removes the function; in a CREATE command it states that there is
 * none. Source: https://www.postgresql.org/docs/17/sql-createforeigndatawrapper.html,
 * https://www.postgresql.org/docs/17/sql-alterforeigndatawrapper.html, https://www.postgresql.org/docs/17/sql-createlanguage.html.
 *
 * @visibility SqlSemantics
 * @example Stating that there is no validator
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionClause(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionRole::Validator))->function // => null
 */
final class FunctionClause implements Node
{
    use Snapshot;

    /**
     * @param FunctionRole $role The role of the function
     * @param DottedName|null $function The function name; null for the NO form
     */
    public function __construct(public readonly FunctionRole $role, public readonly ?DottedName $function = null)
    {
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        if ($this->function === null) {
            $out->keyword('NO', $this->role->value);

            return;
        }
        $out->keyword($this->role->value)->node($this->function);
    }
}
