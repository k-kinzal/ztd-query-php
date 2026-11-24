<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Alter;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\DefinitionSource;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableDeclarationSql;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A new ordinary column at the end of a resolved SQLite table.
 *
 * @visibility public
 * @example Adding a column with a default
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $declarations = $semantics->context(['CREATE TABLE t (id INTEGER)']);
 *     $semantics->analyze('ALTER TABLE t ADD message TEXT DEFAULT NULL', $declarations)->toString() // => 'ALTER TABLE "main"."t" ADD COLUMN "message" TEXT DEFAULT NULL'
 */
final class AddColumn implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<Identifier> The prior declared names in ordinal order
     */
    public readonly array $priorHeading;

    /**
     * Fixes the absolute target, prior heading and appended column.
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
        Check::input($column->generated === null, 'An ordinary column addition cannot have a generation expression.');
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
     * Emits the absolute target and the appended column definition.
     */
    public function toString(): string
    {
        $definition = (new TableDeclarationSql())->column($this->column, new SelectSql(), new DefinitionSource([$this->column->name]));

        return 'ALTER TABLE ' . $this->schema->quoted() . '.' . $this->table->quoted() . ' ADD COLUMN ' . $definition;
    }
}
