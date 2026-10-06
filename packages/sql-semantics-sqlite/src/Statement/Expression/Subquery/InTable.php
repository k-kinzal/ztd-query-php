<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Rules\Expression\RowValueUse;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

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
 * @visibility public
 * @example Reading the relation of a membership test
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL)');
 *     $query = $semantics->analyze('SELECT 1 IN t', [$table]);
 *     [$query->statement->columns[0]->expression->table->name->value, $query->facts->relation($query->statement->columns[0]->expression)->table->table === $table->declarations()[0], $query->field(0)->nullability] // => ['t', true, \SqlSemantics\Statement\Type\Nullability::NotNull]
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
