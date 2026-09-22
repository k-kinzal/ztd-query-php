<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlSemantics\Ast\Declaration\TableDefinition as ParsedTable;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Table;

/**
 * Classifies table persistence, storage and dialect options.
 *
 * @visibility SqlSemantics
 */
final class TablePropertiesBinder
{
    /**
     * Returns properties for the selected SQL dialect.
     */
    public static function bind(ParsedTable $table, Scope $scope): Table\Properties
    {
        $options = $table->options;
        if ($scope->identifiers->dialect === Dialect::Sqlite) {
            OptionBinding::classified($options, ['temporary', 'if_not_exists', 'without_rowid', 'strict']);
            return new Table\SqliteProperties(isset($options['without_rowid']), isset($options['strict']), isset($options['temporary']) || preg_match('/^CREATE (TEMP|TEMPORARY) /i', Tree::text($table->source)) === 1);
        }
        if ($scope->identifiers->dialect === Dialect::MySql) {
            return MySqlTableProperties::bind($options);
        }
        $parameters = StorageParameters::read($table->source, $scope, ['columnDef', 'TableConstraint']);
        OptionBinding::classified($options, ['temporary', 'local', 'global', 'unlogged', 'if_not_exists', 'on_commit', 'tablespace', 'using', ...array_map(static fn ($parameter): string => implode('.', $parameter->name->parts), $parameters)]);
        $words = strtoupper(Tree::text($table->source));
        $tablePosition = strpos($words, 'TABLE');
        return new Table\PostgreSqlProperties(
            str_contains(substr($words, 0, $tablePosition === false ? 0 : $tablePosition), 'TEMP') ? Table\Persistence::Temporary : (isset($options['unlogged']) ? Table\Persistence::Unlogged : Table\Persistence::Permanent),
            str_contains($words, 'ON COMMIT DROP') ? Table\CommitAction::Drop : (str_contains($words, 'ON COMMIT DELETE ROWS') ? Table\CommitAction::DeleteRows : Table\CommitAction::PreserveRows),
            OptionBinding::string($options, 'using'),
            OptionBinding::string($options, 'tablespace'),
            $parameters,
        );
    }
}
