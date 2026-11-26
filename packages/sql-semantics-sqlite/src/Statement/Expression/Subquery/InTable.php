<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\CommonTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Rules\Expression\RowValueUse;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

/**
 * A membership test against the rows of a named table or of a table-valued function, optionally negated.
 *
 * Rule: SQLITE-IN-TABLE-001. `x IN t` reads every row of the relation `t`
 * and `x IN f(args)` every row of a table-valued function. The use of the
 * relation is recorded as a table use of the operation; without arguments
 * its shape follows SQLITE-TABLE-SHAPE-001, with arguments
 * SQLITE-TABLE-CALL-001. The result is INTEGER; it can be NULL when the
 * operand or a compared column can be, and depends on the missing inputs
 * when the shape of the relation is open. The relation has as many columns
 * as the operand has values (SQLITE-ROW-VALUE-USE-001). The operand may not end in an
 * operator weaker than the equality group (SQLITE-PRECEDENCE-001).
 * Source: https://sqlite.org/lang_expr.html#the_in_and_not_in_operators.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class InTable implements Scalar
{
    use Snapshot;

    /**
     * @var list<Scalar>|null The arguments of a table-valued function; null when no argument list is written
     */
    public readonly ?array $arguments;

    /**
     * @param Scalar $operand The tested expression
     * @param QualifiedName $table The relation or function name
     * @param list<Scalar>|null $arguments The arguments of a table-valued function; null when no argument list is written
     * @param bool $negated Whether NOT is written before IN
     */
    public function __construct(public readonly Scalar $operand, public readonly QualifiedName $table, ?array $arguments = null, public readonly bool $negated = false)
    {
        $this->arguments = $arguments === null ? null : Check::listOf($arguments, Scalar::class, 'The arguments of a table-valued function are expressions.');
        Check::input((new Precedence())->closing($operand) >= Precedence::EQUALITY, 'The operand needs parentheses to keep its place.');
    }

    /**
     * Derives the operand, the arguments, the use of the relation and the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $nullability = $operand->nullability;
        foreach ($this->arguments ?? [] as $argument) {
            $derivation->scalar($argument, $environment);
        }
        $resolution = $derivation->table($this->table, $environment);
        $fact = $this->arguments === null || $resolution instanceof DeclaredTable || $resolution instanceof CommonTable
            ? (new TableShapes())->fact($derivation, $this->table, $environment)
            : new RelationFact(new RowShape([], [new UndeclaredRoutine($this->table)]));
        $derivation->target($this, $fact);
        if ($fact->shape->complete()) {
            (new RowValueUse())->membership($operand, count($fact->shape->slots), $derivation);
        } else {
            $nullability = $nullability->propagate(Nullability::Dependent);
        }
        foreach ($fact->shape->slots as $slot) {
            $nullability = $nullability->propagate($slot->nullability);
        }

        return new ScalarFact(new Known(Storage::Integer), $nullability);
    }

    /**
     * Writes the test.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('IN');
        if ($this->table->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->table->name, NameUse::Relation);
        if ($this->arguments !== null) {
            $out->glue()->symbol('(')->list($this->arguments)->symbol(')');
        }
    }
}
