<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Spatial;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\SpatialChecks;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `DROP SPATIAL REFERENCE SYSTEM [IF EXISTS] srid`: a request to remove a spatial reference system (MySQL 8.0 and later).
 *
 * Mirrors PT_drop_srs (Sql_cmd_drop_srs). Rule: MYSQL-DROP-SRS-001. An
 * identifier of 0 or above 2^32-1 retains an immutable refusal on the request
 * (MYSQL-SRS-CHECK-001). Execution rejects it before touching the catalog;
 * catalog existence remains a separate consumer condition.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-spatial-reference-system.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Removing a spatial reference system
 *     $drop = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('drop spatial reference system if exists 13000');
 *     [$drop->toString(), $drop->ifExists] // => ['DROP SPATIAL REFERENCE SYSTEM IF EXISTS 13000', true]
 */
final class DropSpatialReference extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * The intrinsic identifier constraint, or null for a modifiable identifier.
     */
    public readonly ?\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem $problem;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param Numeral $srid The spatial reference system identifier
     */
    public function __construct(public readonly bool $ifExists, public readonly Numeral $srid, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        \SqlSemantics\Diagnostic\Check::input((new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->integral($srid), 'A spatial reference identifier is an integer token.');
        $this->problem = (new SpatialChecks())->identifier($srid);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
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
