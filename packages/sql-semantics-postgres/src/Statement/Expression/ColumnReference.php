<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ImproperName;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\ColumnLookup;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A dotted name used as a value: a request to resolve it as a column at its position.
 *
 * Mirrors PostgreSQL's `ColumnRef` node: the parts are kept as written, and
 * which of them name a schema, a relation, a column or a field is decided
 * when the reference is resolved.
 *
 * Rule: PG-COLUMN-REF-001 (slice — the expression family completes or
 * replaces this). One part is a column of a visible relation. Two to four
 * parts are a column qualified by relation, schema and catalog, resolved by
 * CORE-COLUMN-LOOKUP-001. Facts: a resolved reference has the type and NULL
 * fact of its slot; a conditional reference depends on its missing inputs; a
 * missing or ambiguous reference is invalid. A reference whose leading parts
 * resolve as a column, so that the rest selects a field of a composite value,
 * has no rule yet. Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-COLUMN-REFS.
 * Status: Specified.
 *
 * @visibility public
 * @example Reading a qualified column reference
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT t.a FROM t');
 *     [count($query->statement->targets[0]->expression->parts), $query->field(0)->name->value] // => [2, 'a']
 */
final class ColumnReference implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The parts of the name as written, the column or field last
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
     * Resolves the reference in the environment and derives its facts from the slot found.
     *
     * @throws ImplementationGap When the reference selects a field of a composite column
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $count = count($this->parts);
        if ($count > 4) {
            $problem = new ImproperName(new DottedName($this->parts));
            $derivation->report($problem);

            return new ScalarFact(new Invalid($problem), Nullability::Dependent);
        }
        $lookup = new ColumnLookup();
        $qualifier = $count === 1 ? null : (new DottedName(array_slice($this->parts, 0, -1)))->qualified();
        $resolution = $lookup->find($environment, $this->parts[$count - 1], $qualifier);
        if ($resolution instanceof ResolvedColumn) {
            return new ScalarFact($resolution->slot->type, $resolution->slot->nullability, $resolution);
        }
        if ($resolution instanceof ConditionalColumn) {
            return new ScalarFact(new Dependent($resolution->missing), Nullability::Dependent, $resolution);
        }
        for ($length = 1; $length < $count; $length++) {
            $qualifier = $length === 1 ? null : (new DottedName(array_slice($this->parts, 0, $length - 1)))->qualified();
            if (!$lookup->find($environment, $this->parts[$length - 1], $qualifier) instanceof MissingColumn) {
                throw ImplementationGap::rule('PG-COLUMN-REF-001: field selection from a composite column');
            }
        }
        Check::invariant($resolution instanceof Diagnostic, 'A column lookup resolves, depends on missing inputs, or reports a problem.');

        return new ScalarFact(new Invalid($resolution), Nullability::Dependent, $resolution);
    }

    /**
     * Writes the parts separated by dots.
     */
    public function render(Output $out): void
    {
        (new Spelling())->dotted($out, $this->parts);
    }
}
