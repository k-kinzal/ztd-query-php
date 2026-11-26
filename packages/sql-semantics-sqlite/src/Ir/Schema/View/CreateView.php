<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\View;

use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Rows\Compound;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * An explicitly addressed SQLite view with a resolved query and effective column names.
 *
 * @visibility public
 * @example Defining a view
 *     $ir = (new \SqlSemantics\Platform\Sqlite\Semantics())->analyze('CREATE VIEW results AS SELECT 7 AS answer');
 *     $ir->toString() // => 'CREATE VIEW "main"."results" ("answer") AS SELECT 7 AS "answer"'
 */
final class CreateView implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<Identifier> The effective view column names in query order
     */
    public readonly array $columns;

    /**
     * Establishes the naming, source placement and no-parameter conditions of a view.
     * @param list<Identifier> $columns The effective names in query order
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $view,
        array $columns,
        public readonly Compound|GroupedSelect|Select|ValuesQuery $query,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input($schema->value !== '', 'A CREATE VIEW needs a nonempty schema identifier.');
        Check::input(!str_starts_with($view->key(), 'sqlite_'), 'A CREATE VIEW cannot use the reserved SQLite namespace.');
        $names = Check::listOf($columns, Identifier::class, 'A CREATE VIEW needs effective column names.', 1);
        Check::input(count($names) <= 2000, 'View output count exceeds the SQLite profile.');
        if ($query instanceof Compound) {
            (new CompoundViewBounds())->check($schema, $query, count($names), strlen((new ViewSql())->header($schema, $view, $names, $ifNotExists)));
        } else {
            (new ViewQueryBounds())->check($schema, $query, count($names));
        }
        $seen = [];
        foreach ($names as $name) {
            $key = $name->key();
            Check::input($key !== 'true' && $key !== 'false', 'A view name list must use the effective name of a truth word.');
            Check::input(!isset($seen[$key]), 'Effective view column names must be unique.');
            $seen[$key] = true;
        }
        $this->columns = $names;
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return $this->query->profileId();
    }

    /**
     * Renders the checked view definition and its fixed column names.
     */
    public function toString(): string
    {
        return (new ViewSql())->header($this->schema, $this->view, $this->columns, $this->ifNotExists) . $this->query->toString();
    }
}
