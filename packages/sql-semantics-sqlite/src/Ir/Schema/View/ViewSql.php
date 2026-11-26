<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\View;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;

/**
 * Writes the literal prefix common to every completed SQLite CREATE VIEW.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ViewSql
{
    /**
     * Quotes the absolute destination and each effective name.
     * @param non-empty-list<Identifier> $names The effective names in query order
     */
    public function header(Identifier $schema, Identifier $view, array $names, bool $ifNotExists): string
    {
        $columns = [];
        foreach ($names as $name) {
            $columns[] = $name->quoted();
        }

        return 'CREATE VIEW ' . ($ifNotExists ? 'IF NOT EXISTS ' : '')
            . $schema->quoted() . '.' . $view->quoted() . ' (' . implode(', ', $columns) . ') AS ';
    }
}
