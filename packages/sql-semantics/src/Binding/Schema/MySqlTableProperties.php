<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlSemantics\Ast\Declaration\TableDefinition as ParsedTable;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\PartitionSchemes;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\TableOptions;
use SqlSemantics\Schema\Table\MySqlProperties;

/**
 * Converts MySQL table options and partitioning to named, typed properties.
 *
 * @visibility SqlSemantics
 */
final class MySqlTableProperties
{
    /**
     * Reads every table option by keyword and binds the partitioning against the declared table.
     * @throws \SqlSemantics\InvalidSql
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public static function bind(ParsedTable $table, Scope $scope): MySqlProperties
    {
        $options = Tree::outer($table->source, ['create_table_option', 'partition_clause', 'partition']);
        $options = array_values(array_filter($options, static fn ($option): bool => $option->name === 'create_table_option'));
        return TableOptions::read($options, $scope->identifiers, isset($table->options['temporary']), PartitionSchemes::read($table->source, $scope))[0];
    }
}
