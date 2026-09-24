<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Relation;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\ColumnReader;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Schema\ColumnBinder;
use SqlSemantics\Binding\Schema\ConstraintBinder;
use SqlSemantics\Binding\Schema\StorageParameters;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\Definition\ForeignOperands;
use SqlSemantics\Binding\Statement\Definition\WrapperOptions;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Relation\Column;
use SqlSemantics\Model\Definition\Relation\Identity;
use SqlSemantics\Model\Definition\Relation\Storage;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;

/**
 * Binds the ALTER COLUMN, ADD COLUMN, and DROP COLUMN commands of ALTER TABLE.
 * @visibility SqlSemantics
 */
final class ColumnActions
{
    /**
     * Dispatches an ALTER [COLUMN] command by the keyword that follows the column.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $command, Scope $scope, QueryContext $context): RelationAction
    {
        $words = ObjectAddresses::words($command);
        $columnNode = Tree::child($command, ['ColId', 'Iconst']) ?? throw new UnclassifiedSql('A column action requires its column.');
        $column = $context->tables->identifiers->name($columnNode->tokens()[0]);
        $tail = array_slice($words, (Tree::child($command, ['opt_column']) === null ? 1 : 2) + 1);
        $identity = Tree::child($command, ['alter_identity_column_option_list']);
        if ($identity !== null) {
            return IdentityActions::alter($column, $identity, $scope, $context);
        }
        return match ($tail[0] ?? '') {
            'SET' => self::set($column, $columnNode, $command, $tail, $scope, $context),
            'DROP' => self::dropProperty($column, $command, $tail),
            'ADD' => IdentityActions::add($column, $command, $scope, $context),
            'TYPE' => self::type($column, $command, $scope, $context),
            'RESET' => new Storage\ResetColumnOptions($column, RelationAlterations::parameterNames($command, $context)),
            'OPTIONS' => new Storage\SetColumnForeignOptions($column, Collections::nonEmpty(array_map(static fn (Node $change) => WrapperOptions::change($change, $context->tables->identifiers), Tree::outer($command, ['alter_generic_option_elem'])))),
            default => throw new UnclassifiedSql('Unclassified column action: ' . Tree::text($command)),
        };
    }

    /**
     * SET selects a default, nullability, expression, statistics, options, storage, compression, or type.
     * @param list<string> $tail
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function set(string $column, Node $columnNode, Node $command, array $tail, Scope $scope, QueryContext $context): RelationAction
    {
        $expression = static fn (): \SqlSemantics\Model\Expression => (new ExpressionBinder())->bind(Tree::outer($command, ['a_expr'])[0] ?? throw new UnclassifiedSql('The column action requires its expression.'), $scope);
        return match ($tail[1] ?? '') {
            'DEFAULT' => new Column\ColumnDefaultChange($column, $expression()),
            'NOT' => new Column\SetColumnNullability($column, Nullability::NotNull),
            'EXPRESSION' => new Column\SetColumnExpression($column, $expression()),
            'STATISTICS' => self::statistics($column, $columnNode, $command),
            '(' => new Storage\SetColumnOptions($column, Collections::nonEmpty(StorageParameters::read($command, $scope))),
            'STORAGE' => new Column\SetColumnStorage($column, Column\ColumnStorageMode::tryFrom(strtoupper($context->tables->identifiers->name($command->tokens()[count($command->tokens()) - 1]))) ?? throw new InvalidSql(InputViolation::ColumnStorage, $command)),
            'COMPRESSION' => new Column\SetColumnCompression($column, Column\ColumnCompression::tryFrom(strtolower($context->tables->identifiers->name($command->tokens()[count($command->tokens()) - 1]))) ?? throw new InvalidSql(InputViolation::ColumnCompression, $command)),
            'DATA' => self::type($column, $command, $scope, $context),
            default => throw new UnclassifiedSql('Unclassified column SET action: ' . Tree::text($command)),
        };
    }

    /**
     * DROP removes the default, the NOT NULL requirement, the generation expression, or the identity.
     * @param list<string> $tail
     * @throws UnclassifiedSql
     */
    public static function dropProperty(string $column, Node $command, array $tail): RelationAction
    {
        $ifExists = in_array('IF_P', array_map(static fn ($token): string => $token->name, $command->tokens()), true);
        return match ($tail[1] ?? '') {
            'DEFAULT' => new Column\ColumnDefaultChange($column, null),
            'NOT' => new Column\SetColumnNullability($column, Nullability::MaybeNull),
            'EXPRESSION' => new Column\DropColumnExpression($column, $ifExists),
            'IDENTITY' => new Identity\DropColumnIdentity($column, $ifExists),
            default => throw new UnclassifiedSql('Unclassified column DROP action: ' . Tree::text($command)),
        };
    }

    /**
     * A numeric column addresses an index expression by position; DEFAULT restores the system target.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function statistics(string $column, Node $columnNode, Node $command): Column\SetColumnStatistics
    {
        $value = Tree::child($command, ['set_statistics_value']) ?? throw new UnclassifiedSql('SET STATISTICS requires its target.');
        $text = str_replace([' ', '_'], '', Tree::text($value));
        if (strtoupper($text) !== 'DEFAULT' && preg_match('/^[+-]?[0-9]{1,9}$/D', $text) !== 1) {
            throw new InvalidSql(InputViolation::StatisticsTarget, $value);
        }
        $position = $columnNode->name === 'Iconst' ? str_replace('_', '', Tree::text($columnNode)) : null;
        if ($position !== null && (strlen($position) > 9 || (int) $position < 1 || (int) $position > 32767)) {
            throw new InvalidSql(InputViolation::ColumnPosition, $columnNode);
        }
        try {
            return new Column\SetColumnStatistics($position === null ? $column : (int) $position, strtoupper($text) === 'DEFAULT' ? -1 : (int) $text);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::StatisticsTarget, $value, $error);
        }
    }

    /**
     * A type change may carry a collation and a conversion expression.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function type(string $column, Node $command, Scope $scope, QueryContext $context): Column\ColumnTypeChange
    {
        $type = (new TypeReader(Dialect::PostgreSql))->read(Tree::child($command, ['Typename']) ?? throw new UnclassifiedSql('A type change requires its type.'));
        $collate = Tree::child($command, ['opt_collate_clause']);
        $collation = $collate === null || !Tree::hasTokens($collate) ? null : ObjectAddresses::name(Tree::child($collate, ['any_name']) ?? throw new UnclassifiedSql('COLLATE requires a collation.'), $context, 2);
        $using = Tree::child($command, ['alter_using']);
        $conversion = $using === null || !Tree::hasTokens($using) ? null : (new ExpressionBinder())->bind(Tree::child($using, ['a_expr']) ?? throw new UnclassifiedSql('USING requires an expression.'), $scope);
        return new Column\ColumnTypeChange($column, $type, $collation, $conversion);
    }

    /**
     * DROP [COLUMN] [IF EXISTS] name [CASCADE | RESTRICT].
     * @throws UnclassifiedSql
     */
    public static function drop(Node $command, QueryContext $context): Column\DropColumn
    {
        $columnNode = Tree::child($command, ['ColId']) ?? throw new UnclassifiedSql('DROP COLUMN requires its column.');
        $behavior = Tree::child($command, ['opt_drop_behavior']);
        return new Column\DropColumn($context->tables->identifiers->name($columnNode->tokens()[0]), in_array('IF_P', array_map(static fn ($token): string => $token->name, $command->tokens()), true), $behavior === null ? DropBehavior::Default : DropBehavior::from(strtoupper(Tree::text($behavior))));
    }

    /**
     * ADD [COLUMN] [IF NOT EXISTS] binds the new column and its constraints against the extended declaration.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function add(Node $command, Scope $scope, QueryContext $context): Column\AddColumn
    {
        $definition = Tree::child($command, ['columnDef']) ?? throw new UnclassifiedSql('ADD COLUMN requires its column declaration.');
        [$parsed, $constraints] = (new ColumnReader($context->tables->identifiers))->read($definition, Tree::outer($definition, ['ColConstraint']));
        $newColumn = new \SqlSemantics\Schema\ColumnDefinition($parsed->name, $parsed->type, $parsed->nullability, $parsed->source);
        $relations = $scope->relations;
        $declared = $relations === [] ? $scope : self::extended($relations[0], $newColumn, $command, $context);
        $column = ColumnBinder::bind($parsed, $declared);
        $bound = array_map(static fn ($constraint): \SqlSemantics\Schema\TableConstraint => ConstraintBinder::bind($constraint, $declared), $constraints);
        $options = array_map(static fn (Node $option) => ForeignOperands::option($option, $context->tables->identifiers), Tree::outer($definition, ['generic_option_elem']));
        return new Column\AddColumn($column, $bound, in_array('IF_P', array_map(static fn ($token): string => $token->name, $command->tokens()), true), $options);
    }

    /**
     * Builds a scope whose only relation is the altered table extended by the new column.
     */
    public static function extended(\SqlSemantics\Model\TableUse $relation, \SqlSemantics\Schema\ColumnDefinition $newColumn, Node $source, QueryContext $context): Scope
    {
        $target = $relation->declaration;
        $table = new \SqlSemantics\Schema\TableDefinition($target->schema, $target->name, [...$target->columns, $newColumn], $target->constraints, $target->source);
        return new Scope($context->tables->identifiers, [new \SqlSemantics\Model\Relation\TableReference('declaration', 'declaration', $table, new \SqlSemantics\Model\Relation\QualifiedName($table->schema === '' ? [$table->name] : [$table->schema, $table->name]), null, $source)], queries: $context);
    }
}
