<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ColumnFacts;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

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
 * @visibility public
 * @example Reading a qualified column reference
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT t.a FROM t');
 *     [count($query->statement->targets[0]->expression->parts), $query->field(0)->name->value] // => [2, 'a']
 */
final class ColumnReference implements Scalar, OutputNaming
{
    use \SqlSemantics\Statement\Snapshot;

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
