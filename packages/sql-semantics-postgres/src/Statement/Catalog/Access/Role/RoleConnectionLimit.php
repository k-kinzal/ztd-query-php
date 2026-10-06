<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The role option CONNECTION LIMIT: how many concurrent connections the role can make.
 *
 * The limit is a signed integer; -1 means no limit, and a smaller number is
 * rejected by the server, which the statement reports.
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html.
 *
 * @visibility public
 * @example Reading the limit
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE joe CONNECTION LIMIT 5');
 *     $operation->statement->options[0]->magnitude->digits // => '5'
 */
final class RoleConnectionLimit implements RoleOption
{
    use Snapshot;

    /**
     * The magnitude of the limit.
     */
    public readonly IntegerConstant $magnitude;

    /**
     * @param SignedNumber $limit The limit, a signed integer
     */
    public function __construct(public readonly SignedNumber $limit)
    {
        $magnitude = $limit->magnitude;
        Check::input($magnitude instanceof IntegerConstant, 'A connection limit is an integer.');
        $this->magnitude = $magnitude;
    }

    /**
     * Answers the option a connection limit fills.
     */
    public function option(): string
    {
        return 'connectionlimit';
    }

    /**
     * Tells whether the server accepts the limit: -1 or more.
     */
    public function acceptable(): bool
    {
        return !$this->limit->negative || $this->magnitude->digits === '0' || $this->magnitude->digits === '1';
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('CONNECTION', 'LIMIT')->node($this->limit);
    }
}
