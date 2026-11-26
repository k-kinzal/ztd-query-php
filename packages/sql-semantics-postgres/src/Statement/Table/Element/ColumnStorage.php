<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The STORAGE clause of a column: the storage method of its values, or DEFAULT.
 *
 * Mirrors the `storage` field of PostgreSQL's `ColumnDef` (and of
 * `AT_SetStorage`). The method name is checked by the server.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility SqlSemantics
 */
final class ColumnStorage implements Node
{
    use Snapshot;

    /**
     * @param Name|null $method The method; null for DEFAULT
     */
    public function __construct(public readonly ?Name $method)
    {
    }

    /**
     * Writes STORAGE and the method or DEFAULT.
     */
    public function render(Output $out): void
    {
        $out->keyword('STORAGE');
        if ($this->method === null) {
            $out->keyword('DEFAULT');
        } else {
            $out->name($this->method);
        }
    }
}
