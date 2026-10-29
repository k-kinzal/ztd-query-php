<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Spatial;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Server\SpatialChecks;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `CREATE [OR REPLACE] SPATIAL REFERENCE SYSTEM [IF NOT EXISTS] srid attribute …`: a request to define a spatial reference system (MySQL 8.0 and later).
 *
 * Mirrors PT_create_srs (Sql_cmd_create_srs). Rule: MYSQL-CREATE-SRS-001.
 * The request retains attributes in written order, including requests missing a
 * mandatory attribute or using an unmodifiable identifier. Its immutable problem
 * records the first declaration constraint; execution must reject it before any
 * catalog change. Repeated attribute roles remain in written order. Syntax
 * invariants still require mutually exclusive modifiers. Numeric refusals retain their operands. A request is not
 * a successfully declared spatial reference system.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-spatial-reference-system.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Retaining repeated attribute roles as an execution condition
 *     $name = new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Name, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('n'));
 *     $second = new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Name, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('other'));
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference(false, false, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('7'), [$name, $second]))->problem->rule // => \SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::RepeatedAttribute
 * @example Defining a spatial reference system
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("create or replace spatial reference system 4120 name 'Greek' organization 'EPSG' identified by 4120 definition 'GEOGCS[]'");
 *     [$create->toString(), $create->diagnostics()] // => ["CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' ORGANIZATION 'EPSG' IDENTIFIED BY 4120 DEFINITION 'GEOGCS[]'", []]
 */
final class CreateSpatialReference extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * The first declaration constraint, computed from this request's operands.
     */
    public readonly ?\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem $problem;

    /**
     * @var list<SpatialAttribute> The attributes in written order
     */
    public readonly array $attributes;

    /**
     * @param bool $orReplace Whether OR REPLACE is written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param Numeral $srid The spatial reference system identifier
     * @param list<SpatialAttribute> $attributes The attributes in written order
     * @throws InvalidConstruction When modifiers conflict
     */
    public function __construct(public readonly bool $orReplace, public readonly bool $ifNotExists, public readonly Numeral $srid, array $attributes, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input(!$orReplace || !$ifNotExists, 'OR REPLACE and IF NOT EXISTS exclude each other.');
        $this->attributes = Check::listOf($attributes, SpatialAttribute::class, 'CREATE SPATIAL REFERENCE SYSTEM takes a list of attributes.');
        $this->problem = (new SpatialChecks())->declaration($srid, $this->attributes);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Reports the problems the server finds while it parses the statement.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new SpatialChecks())->create($derivation, $this->srid, $this->attributes);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->orReplace) {
            $out->keyword('OR', 'REPLACE');
        }
        $out->keyword('SPATIAL', 'REFERENCE', 'SYSTEM');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->node($this->srid);
        foreach ($this->attributes as $attribute) {
            $out->node($attribute);
        }
    }
}
