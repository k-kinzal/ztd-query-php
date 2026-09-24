<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Table;
use SqlSemantics\Model\Ordering;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds the table-level alterations of MySQL ALTER TABLE: RENAME, CONVERT, ORDER BY, and table options.
 * @visibility SqlSemantics
 */
final class TableAlterations
{
    /**
     * Binds RENAME [TO | AS | =] name.
     * @throws InvalidSql
     */
    public static function rename(Node $item, Scope $scope): Table\RenameTable
    {
        $name = Tree::child($item, ['table_ident']) ?? $item;
        return PartitionDefinitions::build(static fn (): Table\RenameTable => new Table\RenameTable(new QualifiedName($scope->identifiers->parts($name))), $item, InputViolation::TableAlteration);
    }

    /**
     * Binds CONVERT TO CHARACTER SET name or DEFAULT, with an optional collation.
     * @throws InvalidSql
     */
    public static function convert(Node $item, Scope $scope): Table\ConvertCharacterSet
    {
        $charset = Tree::child($item, ['charset_name', 'charset_name_or_default']);
        $collate = Tree::child($item, ['opt_collate']);
        $collation = $collate === null ? null : $collate->tokens()[count($collate->tokens()) - 1];
        $default = $charset === null || strtoupper(Tree::text($charset)) === 'DEFAULT';
        return PartitionDefinitions::build(static fn (): Table\ConvertCharacterSet => new Table\ConvertCharacterSet(
            $default ? Table\InheritedCharacterSet::Database : TableOptions::text($charset, $scope->identifiers),
            $collation === null || strtoupper($collation->text) === 'DEFAULT' ? null : TableOptions::text($collation, $scope->identifiers),
        ), $item, InputViolation::TableAlteration);
    }

    /**
     * Binds ORDER BY column [ASC | DESC], ...
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function order(Node $item, Scope $scope): Table\OrderRows
    {
        $orderings = [];
        foreach (Tree::outer($item, ['alter_order_item']) as $entry) {
            $column = Tree::child($entry, ['simple_ident_nospvar']) ?? throw new UnclassifiedSql('ORDER BY requires a column.');
            $words = array_map(static fn (Token $token): string => strtoupper($token->text), $entry->tokens());
            $orderings[] = new Ordering($scope->column(array_values(array_filter($scope->identifiers->parts($column), static fn (string $part): bool => $part !== '')), $column), end($words) === 'DESC');
        }
        return PartitionDefinitions::build(static fn (): Table\OrderRows => new Table\OrderRows(\SqlSemantics\Model\Validation\Collections::nonEmpty($orderings)), $item, InputViolation::TableAlteration);
    }

    /**
     * Binds a space-separated group of table options.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function options(Node $group, Scope $scope): Table\ChangeTableOptions
    {
        [$properties, $resets] = TableOptions::read(Tree::outer($group, ['create_table_option']), $scope->identifiers);
        return PartitionDefinitions::build(static fn (): Table\ChangeTableOptions => new Table\ChangeTableOptions($properties, $resets), $group, InputViolation::TableOption);
    }
}
