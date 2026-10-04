<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * SET ACCESS METHOD: changes the table access method, or to the default one.
 *
 * Mirrors `AT_SetAccessMethod`. PostgreSQL 17 accepts DEFAULT, which is the default_table_access_method
 * setting.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Changing the access method
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t SET ACCESS METHOD heap');
 *     $statement->statement->commands[0]->method->value // => 'heap'
 */
final class SetAccessMethod implements AlterCommand
{
    use Snapshot;

    /**
     * @param Name|null $method The access method; null for DEFAULT
     */
    public function __construct(public readonly ?Name $method)
    {
    }

    /**
     * Derives nothing: the access method is a catalog name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET', 'ACCESS', 'METHOD');
        if ($this->method === null) {
            $out->keyword('DEFAULT');
        } else {
            $out->name($this->method);
        }
    }
}
