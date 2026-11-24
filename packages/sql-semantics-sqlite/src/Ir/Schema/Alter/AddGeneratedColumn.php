<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Alter;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\DefinitionSource;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableDeclarationSql;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
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
     * @var non-empty-list<Identifier> The prior declared fields that can supply the generated value
     */
    public readonly array $priorHeading;

    /**
     * Fixes the absolute target and the storage and inputs of its appended column.
     *
     * @param list<Identifier> $priorHeading The nonempty declared heading of the target
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        array $priorHeading,
        public readonly bool $strict,
        public readonly TableColumn $column,
    ) {
        $heading = Check::listOf($priorHeading, Identifier::class, 'A column addition requires a nonempty ordered prior heading of identifiers.', 1);
        (new AppendColumnBounds())->check($schema, $table, $heading, $strict, $column);
        $generation = $column->generated;
        Check::input($generation !== null, 'A generated column addition needs a generation expression.');
        (new ExpressionScope())->check([count($heading)], [[$generation->expression, 0, 1]], false);
        $this->priorHeading = $heading;
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
        $definition = (new TableDeclarationSql())->column($this->column, new SelectSql(), new DefinitionSource($this->priorHeading));

        return 'ALTER TABLE ' . $this->schema->quoted() . '.' . $this->table->quoted() . ' ADD COLUMN ' . $definition;
    }
}
