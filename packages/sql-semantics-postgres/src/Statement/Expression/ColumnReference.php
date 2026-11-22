<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ColumnFacts;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;

/**
 * A dotted name used as a value: a request to resolve it as a column at its position.
 *
 * Mirrors PostgreSQL's `ColumnRef` node: the parts are kept as written, and
 * which of them name a catalog, a schema, a relation or a column is decided
 * when the reference is resolved.
 *
 * Rule: PG-COLUMN-REF-001. One part is a column of a visible relation, or
 * else the whole row of a visible relation of that name; where the position
 * offers output columns by name (ORDER BY, GROUP BY), a one-part name that
 * matches one of them refers to it. Two to four parts are a column
 * qualified by relation, schema and catalog, resolved by
 * CORE-COLUMN-LOOKUP-001; a dotted name never selects a field of a
 * composite column, which needs `(column).field`. Facts: a resolved
 * reference has the type and NULL fact of its slot; a conditional
 * reference depends on its missing inputs; a missing or ambiguous
 * reference, or a name of more than four parts, is invalid.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-COLUMN-REFS,
 * https://www.postgresql.org/docs/17/rowtypes.html#ROWTYPES-USAGE, https://www.postgresql.org/docs/17/sql-select.html#SQL-ORDERBY.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ColumnReference implements Scalar, OutputNaming
{
    use \SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

    /**
     * @var non-empty-list<Name> The parts of the name as written, the column last
     */
    public readonly array $parts;

    /**
     * @param list<Name> $parts The parts of the name as written; at least one
     */
    public function __construct(array $parts)
    {
        $this->parts = Check::listOf($parts, Name::class, 'A column reference has at least one name part.', 1);
    }

    /**
     * Names an unaliased result column after the last part.
     */
    public function outputName(): Name
    {
        return $this->parts[count($this->parts) - 1];
    }

    /**
     * Resolves the reference in the environment and derives its facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new ColumnFacts())->derive($derivation, $environment, $this->parts);
    }

    /**
     * Writes the parts separated by dots.
     */
    public function render(Output $out): void
    {
        (new Spelling())->dotted($out, $this->parts);
    }
}
