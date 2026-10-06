<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Spatial;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\SpatialChecks;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `DROP SPATIAL REFERENCE SYSTEM [IF EXISTS] srid`: a request to remove a spatial reference system (MySQL 8.0 and later).
 *
 * Mirrors PT_drop_srs (Sql_cmd_drop_srs). Rule: MYSQL-DROP-SRS-001. An
 * identifier of 0 or above 2^32-1 is a diagnostic (MYSQL-SRS-CHECK-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-spatial-reference-system.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Removing a spatial reference system
 *     $drop = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('drop spatial reference system if exists 13000');
 *     [$drop->toString(), $drop->statement->ifExists] // => ['DROP SPATIAL REFERENCE SYSTEM IF EXISTS 13000', true]
 */
final class DropSpatialReference implements Statement
{
    use Snapshot;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param Numeral $srid The spatial reference system identifier
     */
    public function __construct(public readonly bool $ifExists, public readonly Numeral $srid)
    {
    }

    /**
     * Reports an identifier the server rejects.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new SpatialChecks())->srid($derivation, $this->srid);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'SPATIAL', 'REFERENCE', 'SYSTEM');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->node($this->srid);
    }
}
