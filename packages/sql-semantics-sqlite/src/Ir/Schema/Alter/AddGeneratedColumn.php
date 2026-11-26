<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Alter;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckConstraint;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableDeclarationSql;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * An appended generated column bound to the prior declared slots of an ordinary table.
 *
 * @visibility public
 * @example Adding a generated column over a resolved member
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $declarations = $semantics->context(['CREATE TABLE amounts (n INTEGER)']);
 *     $semantics->analyze('ALTER TABLE amounts ADD twice INTEGER AS (n + n)', $declarations)->column->generated?->mode->value // => 'VIRTUAL'
 */
final class AddGeneratedColumn implements SemanticsIR
{
    use Snapshot;

    /**
     * The appended column's zero-based position in the resolved table.
     */
    public readonly int $slot;

    /**
     * @var array<int, Identifier> The declared names selected by the checked operands
     */
    public readonly array $bindings;

    /**
     * @var list<CheckConstraint> The inline conditions in evaluation order
     */
    public readonly array $checks;

    /**
     * Fixes the absolute target and the storage and inputs of its appended column.
     *
     * @param list<Identifier> $priorHeading The nonempty declared heading of the target
     * @param list<CheckConstraint> $checks The checked conditions on the appended column
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        array $priorHeading,
        public readonly bool $strict,
        public readonly TableColumn $column,
        array $checks = [],
    ) {
        $heading = Check::listOf($priorHeading, Identifier::class, 'A column addition requires a nonempty ordered prior heading of identifiers.', 1);
        $conditions = Check::listOf($checks, CheckConstraint::class, 'An appended column requires an ordered list of checked conditions.');
        $bounds = new AppendColumnBounds();
        $references = $bounds->check($schema, $table, $heading, $strict, $column, $conditions);
        $generation = $column->generated;
        Check::input($generation !== null, 'A generated column addition needs a generation expression.');
        $inputs = (new ExpressionScope())->check([count($heading)], [[$generation->expression, 0, 1]], false);
        $this->slot = count($heading);
        $this->bindings = $bounds->bindings([...$heading, $column->name], [...$references, ...$inputs]);
        $this->checks = $conditions;
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the generated value with every input named by its checked prior slot.
     */
    public function toString(): string
    {
        $definition = (new TableDeclarationSql())->addition($this->column, $this->bindings, $this->checks);

        return 'ALTER TABLE ' . $this->schema->quoted() . '.' . $this->table->quoted() . ' ADD COLUMN ' . $definition;
    }
}
