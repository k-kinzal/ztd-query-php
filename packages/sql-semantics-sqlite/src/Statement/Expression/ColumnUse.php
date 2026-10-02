<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\ColumnLookup;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A name used as a value: a request to resolve it as a column at its position.
 *
 * The node holds the decoded name and qualifier only. Which occurrence and
 * declaration it denotes is a fact of the operation that contains it.
 *
 * Rule: SQLITE-COLUMN-USE-001. Facts: the resolution of CORE-COLUMN-LOOKUP-001;
 * a resolved use has the type and NULL fact of its slot; a conditional use
 * depends on its missing inputs; a missing or ambiguous use is invalid.
 * Source: https://sqlite.org/lang_expr.html#column_names. Status: Implemented.
 *
 * @visibility public
 * @example Reading a qualified column use
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT t.a FROM t');
 *     [$query->statement->columns[0]->expression->qualifier?->name->value, $query->statement->columns[0]->expression->name->value] // => ['t', 'a']
 */
final class ColumnUse implements Scalar
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param QualifiedName|null $qualifier The relation the use is qualified with
     */
    public function __construct(public readonly Name $name, public readonly ?QualifiedName $qualifier = null)
    {
    }

    /**
     * Resolves the name in the environment and derives its facts from the slot found.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $resolution = (new ColumnLookup())->find($environment, $this->name, $this->qualifier);
        if ($resolution instanceof ResolvedColumn) {
            return new ScalarFact($resolution->slot->type, $resolution->slot->nullability, $resolution);
        }
        if ($resolution instanceof ConditionalColumn) {
            return new ScalarFact(new Dependent($resolution->missing), Nullability::Dependent, $resolution);
        }

        Check::invariant($resolution instanceof Diagnostic, 'A column lookup resolves, depends on missing inputs, or reports a problem.');

        return new ScalarFact(new Invalid($resolution), Nullability::Dependent, $resolution);
    }

    /**
     * Writes the qualifier parts and the name.
     */
    public function render(Output $out): void
    {
        if ($this->qualifier?->schema !== null) {
            $out->name($this->qualifier->schema, NameUse::Qualifier)->symbol('.');
        }
        if ($this->qualifier !== null) {
            $out->name($this->qualifier->name, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name, NameUse::Column);
    }
}
