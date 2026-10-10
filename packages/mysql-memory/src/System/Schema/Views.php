<?php

declare(strict_types=1);

namespace MySqlMemory\System\Schema;

use MySqlMemory\Command\View\ViewText;
use MySqlMemory\Command\View\ViewWrites;
use MySqlMemory\Dictionary\View;
use MySqlMemory\System\Listed;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.VIEWS: one for each view of each database.
 *
 * The definition is the query as SHOW CREATE VIEW writes it, its names qualified by their
 * database. A view is updatable when the server can merge its query into a statement that
 * writes through it; the definer is written as user@host (verified on a live 8.4.7 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-views-table.html.
 *
 * @visibility MySqlMemory
 */
final class Views implements SystemRows
{
    /**
     * Answers a row for each view.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Listed::schemas($reading) as $schema) {
            foreach (Listed::of($schema, $reading) as $name => $view) {
                if (!$view instanceof View) {
                    continue;
                }
                $rows[] = [
                    'TABLE_CATALOG' => 'def',
                    'TABLE_SCHEMA' => $schema->name,
                    'TABLE_NAME' => $name,
                    'VIEW_DEFINITION' => (new ViewText($view->created->facts, '', $view->database))->query($view->definition, true, $view->hints) ?? $view->select,
                    'CHECK_OPTION' => $view->check === '' ? 'NONE' : $view->check,
                    'IS_UPDATABLE' => ViewWrites::block($view) === null ? 'NO' : 'YES',
                    'DEFINER' => $view->definer[0] . '@' . $view->definer[1],
                    'SECURITY_TYPE' => $view->security,
                    'CHARACTER_SET_CLIENT' => $view->charsets[0],
                    'COLLATION_CONNECTION' => $view->charsets[1],
                ];
            }
        }

        return $rows;
    }
}
