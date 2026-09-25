<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\PartitionValidation;
use SqlSemantics\Model\Definition\MySqlTable\Table;
use SqlSemantics\Model\Definition\MySqlTable\TableAlgorithm;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\TableDefinition;

/**
 * Binds MySQL ALTER TABLE: the altered table, its ordered alterations, and the ALGORITHM, LOCK, and validation requests.
 * @visibility SqlSemantics
 */
final class AlterTables
{
    /**
     * @var list<string>
     */
    public const COMMANDS = ['alter_list_item', 'create_table_options_space_separated', 'standalone_alter_commands', 'alter_table_partition_options', 'partitioning', 'remove_partitioning', 'add_partition_rule', 'reorg_partition_rule'];

    /**
     * Binds the statement; alteration expressions see the table's columns and every column the statement declares.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): AlterTableStatement
    {
        $name = Tree::child($statement, ['table_ident']) ?? throw new UnclassifiedSql('ALTER TABLE requires a table.');
        $table = TableOccurrence::resolve($name, $context, $origin->scopeId);
        if (!$table instanceof TableReference) {
            throw new UnclassifiedSql('ALTER TABLE requires a physical table.');
        }
        $commands = self::commands($statement);
        $scope = self::scope($table, $commands, $context);
        ColumnDeclarations::nullKeys($commands, $scope);
        $declaration = $table->declaration;
        $keys = new KeyAlterations($declaration->schema, $table->name->parts);
        $alterations = [];
        foreach ($commands as $command) {
            array_push($alterations, ...self::command($origin, $command, $scope, $keys, $context));
        }
        $identifiers = $context->tables->identifiers;
        $algorithm = AlterPolicies::algorithm($statement, $identifiers);
        if ($algorithm === TableAlgorithm::Instant && in_array($context->tables->schema->grammarVersion, ['mysql-5.6.51', 'mysql-5.7.44'], true)) {
            throw new InvalidSql(InputViolation::AlterAlgorithm, AlterPolicies::last($statement, 'alter_algorithm_option') ?? $statement);
        }
        $validations = array_merge($statement->find('with_validation'), $statement->find('alter_opt_validation'));
        $validation = $validations === [] ? null : PartitionValidation::from(strtoupper(Tree::text($validations[count($validations) - 1])));
        $ignore = array_filter($statement->find('opt_ignore'), Tree::hasTokens(...)) !== [];
        self::counters($declaration, $statement, $context);
        return new AlterTableStatement($origin, $table, $alterations, $algorithm, AlterPolicies::lock($statement, $identifiers), $validation, $ignore);
    }

    /**
     * Rejects an alteration of a known table that leaves it with two AUTO_INCREMENT columns or with one no key begins
     * with, applying the statement to the schema snapshot as SchemaBuilder does; a statement the snapshot cannot apply
     * is left to the other checks.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function counters(TableDefinition $declaration, Node $statement, QueryContext $context): void
    {
        if (!$declaration->resolved || !in_array($declaration, $context->tables->schema->tables, true)) {
            return;
        }
        try {
            (new \SqlSemantics\Binding\Schema\TableAlteration($context->tables))->apply($statement);
        } catch (InvalidSql $invalid) {
            if ($invalid->violation === InputViolation::AutoIncrementKey) {
                throw $invalid;
            }
        } catch (\SqlSemantics\SemanticException) {
            return;
        }
    }

    /**
     * Lists the command nodes of the statement in SQL order; a MySQL 5.6 standalone command is its alter_commands node.
     * @return list<Node>
     */
    public static function commands(Node $statement): array
    {
        $commands = array_values(array_filter(Tree::outer($statement, self::COMMANDS), Tree::hasTokens(...)));
        foreach ($statement->find('alter_commands') as $node) {
            if (($node->children[0] ?? null) instanceof Token) {
                $commands[] = $node;
            }
        }
        return $commands;
    }

    /**
     * Binds one command node into zero or more alterations.
     * @return list<TableAlteration>
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function command(Origin $origin, Node $command, Scope $scope, KeyAlterations $keys, QueryContext $context): array
    {
        $partitioning = in_array($command->name, ['alter_table_partition_options', 'partitioning'], true) ? PartitionSchemes::read($command, $scope) : null;
        return match ($command->name) {
            'alter_list_item' => AlterItems::bind($command, $scope, $keys),
            'create_table_options_space_separated' => [TableAlterations::options($command, $scope)],
            'alter_table_partition_options', 'partitioning', 'remove_partitioning' => [$partitioning === null ? Table\TableCommand::RemovePartitioning : new Table\RepartitionTable($partitioning)],
            default => [PartitionChanges::bind($origin, $command, $scope, $context)],
        };
    }

    /**
     * Builds the column namespace of alteration expressions from the table and the columns the statement declares.
     * @param list<Node> $commands
     */
    public static function scope(TableReference $table, array $commands, QueryContext $context): Scope
    {
        $base = new Scope($context->tables->identifiers, queries: $context);
        $declaration = $table->declaration;
        $columns = ColumnDeclarations::declared($commands, $base, $declaration->columns);
        $extended = new TableDefinition($declaration->schema, $declaration->name, $columns, $declaration->constraints, $declaration->source, $declaration->resolved, $declaration->indexes, $declaration->properties);
        return new Scope($context->tables->identifiers, [new TableReference('declaration', 'declaration', $extended, $table->name, null, $table->source)], queries: $context);
    }
}
