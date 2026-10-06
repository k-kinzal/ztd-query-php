<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * The persistence written between CREATE and the kind of object: temporary, unlogged or permanent.
 *
 * Mirrors `OptTemp` (`RELPERSISTENCE_TEMP`, `UNLOGGED`, `PERMANENT`). TEMP and
 * TEMPORARY are the same request, and LOCAL and GLOBAL change nothing
 * (GLOBAL is deprecated); the spelling is kept because the grammar keeps it.
 * Permanent is the absence of these words.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading the persistence of a table
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE LOCAL TEMP TABLE t (a int)');
 *     [$create->statement->persistence->temporary(), $create->declarations()[0]->name->schema->value] // => [true, 'pg_temp']
 */
enum Persistence: string implements Node
{
    case Permanent = '';
    case Temporary = 'TEMPORARY';
    case Temp = 'TEMP';
    case LocalTemporary = 'LOCAL TEMPORARY';
    case LocalTemp = 'LOCAL TEMP';
    case GlobalTemporary = 'GLOBAL TEMPORARY';
    case GlobalTemp = 'GLOBAL TEMP';
    case Unlogged = 'UNLOGGED';

    /**
     * Tells whether the object is temporary: it lives in the session's temporary schema.
     */
    public function temporary(): bool
    {
        return match ($this) {
            self::Permanent, self::Unlogged => false,
            self::Temporary, self::Temp, self::LocalTemporary, self::LocalTemp, self::GlobalTemporary, self::GlobalTemp => true,
        };
    }

    /**
     * Writes the words, none for a permanent object.
     */
    public function render(Output $out): void
    {
        if ($this !== self::Permanent) {
            $out->keyword(...explode(' ', $this->value));
        }
    }
}
