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
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(ParsedTable $table, Scope $scope): Table\Properties
    {
        $options = $table->options;
        if ($scope->identifiers->dialect === Dialect::Sqlite) {
            OptionBinding::classified($options, ['temporary', 'if_not_exists', 'without_rowid', 'strict']);
            return new Table\SqliteProperties(isset($options['without_rowid']), isset($options['strict']), isset($options['temporary']) || preg_match('/^CREATE (TEMP|TEMPORARY) /i', Tree::text($table->source)) === 1);
        }
        if ($scope->identifiers->dialect === Dialect::MySql) {
            return MySqlTableProperties::bind($table, $scope);
        }
        $parameters = StorageParameters::read($table->source, $scope, ['columnDef', 'TableConstraint']);
        OptionBinding::classified($options, ['temporary', 'local', 'global', 'unlogged', 'if_not_exists', 'on_commit', 'tablespace', 'using', 'partition_by', ...array_map(static fn ($parameter): string => implode('.', $parameter->name->parts), $parameters)]);
        $words = strtoupper(Tree::text($table->source));
        $tablePosition = strpos($words, 'TABLE');
        $partitioning = PartitionSchemeBinder::read($table->source, $scope);
        if ($partitioning !== null && $parameters !== []) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::PartitionedTable, Tree::outer($table->source, ['OptWith'])[0] ?? $table->source);
        }
        $parents = self::parents($table->source, $scope);
        if ($partitioning !== null && $parents !== []) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::PartitionedTable, Tree::child($table->source, ['OptInherit']) ?? $table->source);
        }
        $persistence = str_contains(substr($words, 0, $tablePosition === false ? 0 : $tablePosition), 'TEMP') ? Table\Persistence::Temporary : (isset($options['unlogged']) ? Table\Persistence::Unlogged : Table\Persistence::Permanent);
        if (isset($options['on_commit']) && $persistence !== Table\Persistence::Temporary) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::CommitAction, Tree::outer($table->source, ['OnCommitOption'])[0] ?? $table->source);
        }
        return new Table\PostgreSqlProperties(
            $persistence,
            str_contains($words, 'ON COMMIT DROP') ? Table\CommitAction::Drop : (str_contains($words, 'ON COMMIT DELETE ROWS') ? Table\CommitAction::DeleteRows : Table\CommitAction::PreserveRows),
            OptionBinding::string($options, 'using'),
            OptionBinding::string($options, 'tablespace'),
            $parameters,
            $partitioning,
            $parents,
        );
    }

    /**
     * Reads INHERITS; a parent named twice or with too many name components is an impossible request.
     *
     * @return list<\SqlSemantics\Model\Relation\QualifiedName>
     * @throws \SqlSemantics\InvalidSql
     */
    public static function parents(\SqlParser\Parser\Node $declaration, Scope $scope): array
    {
        $inherits = Tree::child($declaration, ['OptInherit']);
        $parents = [];
        foreach ($inherits === null ? [] : Tree::outer($inherits, ['qualified_name']) as $node) {
            $parts = $scope->identifiers->parts($node);
            if (count($parts) > 3) {
                throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::CatalogObjectName, $node);
            }
            if (in_array($parts, array_map(static fn (\SqlSemantics\Model\Relation\QualifiedName $parent): array => $parent->parts, $parents), true)) {
                throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::InheritedParent, $node);
            }
            $parents[] = new \SqlSemantics\Model\Relation\QualifiedName($parts);
        }
        return $parents;
    }
}
