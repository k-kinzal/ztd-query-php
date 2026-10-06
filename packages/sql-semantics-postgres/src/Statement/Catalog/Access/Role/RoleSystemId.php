<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The role option SYSID of CREATE ROLE, which the server accepts and ignores.
 *
 * The server keeps no option for it, so writing it twice is no conflict.
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html.
 *
 * @visibility public
 * @example Reading the ignored identifier
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE joe SYSID 100');
 *     [$operation->statement->options[0]->identifier->digits, $operation->statement->options[0]->option()] // => ['100', null]
 */
final class RoleSystemId implements RoleOption
{
    use Snapshot;

    /**
     * @param IntegerConstant $identifier The identifier as written
     */
    public function __construct(public readonly IntegerConstant $identifier)
    {
    }

    /**
     * Answers null: the server keeps no option for SYSID.
     */
    public function option(): ?string
    {
        return null;
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('SYSID')->node($this->identifier);
    }
}
