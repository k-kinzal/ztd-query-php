<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Spatial;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Server\SpatialChecks;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CREATE [OR REPLACE] SPATIAL REFERENCE SYSTEM [IF NOT EXISTS] srid attribute …`: a request to define a spatial reference system (MySQL 8.0 and later).
 *
 * Mirrors PT_create_srs (Sql_cmd_create_srs). Rule: MYSQL-CREATE-SRS-001.
 * The attributes are kept in written order. The problems the server reports
 * while it parses the statement are diagnostics (MYSQL-SRS-CHECK-001). A
 * spatial reference system is no relation, so the statement declares none.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-spatial-reference-system.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Defining a spatial reference system
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("create or replace spatial reference system 4120 name 'Greek' organization 'EPSG' identified by 4120 definition 'GEOGCS[]'");
 *     [$create->toString(), $create->facts->diagnostics] // => ["CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' ORGANIZATION 'EPSG' IDENTIFIED BY 4120 DEFINITION 'GEOGCS[]'", []]
 */
final class CreateSpatialReference implements Statement
{
    use Snapshot;

    /**
     * @var list<SpatialAttribute> The attributes in written order
     */
    public readonly array $attributes;

    /**
     * @param bool $orReplace Whether OR REPLACE is written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param Numeral $srid The spatial reference system identifier
     * @param list<SpatialAttribute> $attributes The attributes in written order
     * @throws InvalidConstruction When OR REPLACE and IF NOT EXISTS are both written
     */
    public function __construct(public readonly bool $orReplace, public readonly bool $ifNotExists, public readonly Numeral $srid, array $attributes)
    {
        Check::input(!$orReplace || !$ifNotExists, 'OR REPLACE and IF NOT EXISTS exclude each other.');
        $this->attributes = Check::listOf($attributes, SpatialAttribute::class, 'CREATE SPATIAL REFERENCE SYSTEM takes a list of attributes.');
    }

    /**
     * Reports the problems the server finds while it parses the statement.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $checks = new SpatialChecks();
        $checks->srid($derivation, $this->srid);
        $checks->attributes($derivation, $this->attributes);
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
