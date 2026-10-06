<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\WholeRows;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A dotted relation name followed by `.*`: every column of a visible relation.
 *
 * Mirrors PostgreSQL's `ColumnRef` node whose last field is `A_Star`. In a
 * select list the query expands it to the relation's columns; as a value
 * elsewhere it is the whole row of the relation.
 *
 * Rule: PG-COLUMN-STAR-001. One to three qualifier parts name the relation
 * as written, with its schema and catalog. Facts: the row type of the
 * relation, whose fields are its columns; a relation that is not visible
 * is reported. An unaliased result column is named after the relation.
 * Source: https://www.postgresql.org/docs/17/rowtypes.html#ROWTYPES-USAGE,
 * https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST. Status: Implemented.
 *
 * @visibility public
 * @example Reading the relation of a star
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT ROW(t.*) FROM t');
 *     $query->toString() // => 'SELECT ROW (t.*) FROM t'
 */
final class ColumnStar implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The relation name as written, the relation last
     */
    public readonly array $qualifiers;

    /**
     * @param list<Name> $qualifiers The relation name as written; at least one part
     */
    public function __construct(array $qualifiers)
    {
        $this->qualifiers = Check::listOf($qualifiers, Name::class, 'A star reference names its relation.', 1);
    }

    /**
     * Names an unaliased result column after the relation.
     */
    public function outputName(): Name
    {
        return $this->qualifiers[count($this->qualifiers) - 1];
    }

    /**
     * Resolves the relation and derives its whole row.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new WholeRows())->star($derivation, $environment, $this->qualifiers);
    }

    /**
     * Writes the relation name, the dot and the star.
     */
    public function render(Output $out): void
    {
        (new Spelling())->dotted($out, $this->qualifiers);
        $out->symbol('.')->symbol('*');
    }
}
