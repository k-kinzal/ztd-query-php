<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

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
 * The node holds the decoded column name and its optional table and database
 * qualifiers. Which occurrence and declaration it denotes is a fact of the
 * operation that contains it.
 *
 * Rule: MYSQL-COLUMN-USE-001. Facts: the resolution of CORE-COLUMN-LOOKUP-001
 * over the relation occurrences of the position; a resolved use has the type
 * and NULL fact of its slot; a conditional use depends on its missing inputs;
 * a missing or ambiguous use is invalid and is a diagnostic. Positions that
 * also admit select-list aliases or stored program variables hand those to
 * the lookup through their environment. Terminates: the lookup is finite.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/identifier-qualifiers.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a qualified column use
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT shop.t.a FROM shop.t');
 *     $use = $query->statement->items[0]->expression;
 *     [$use->qualifier?->schema?->value, $use->qualifier?->name->value, $use->name->value] // => ['shop', 't', 'a']
 */
final class ColumnUse implements Scalar
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param QualifiedName|null $qualifier The table the use is qualified with, and the database of that table when written
     */
    public function __construct(public readonly Name $name, public readonly ?QualifiedName $qualifier = null)
    {
        Check::input($qualifier === null || $qualifier->catalog === null, 'A column is qualified by a table and at most a database.');
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
