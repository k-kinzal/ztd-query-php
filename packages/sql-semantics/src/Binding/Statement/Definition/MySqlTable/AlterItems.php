<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;

/**
 * Dispatches one ALTER TABLE list item by its leading keyword.
 * @visibility SqlSemantics
 */
final class AlterItems
{
    /**
     * Binds an item; ALGORITHM and LOCK items of MySQL 5.6 are statement requests and bind to nothing here.
     * @return list<TableAlteration>
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Node $item, Scope $scope, KeyAlterations $keys): array
    {
        $options = Tree::child($item, ['create_table_options_space_separated']);
        if ($options !== null) {
            return [TableAlterations::options($options, $scope)];
        }
        if (Tree::child($item, ['alter_algorithm_option', 'alter_lock_option']) !== null) {
            return [];
        }
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $item->tokens());
        return [match ($words[0]) {
            'ADD', 'CHANGE', 'MODIFY', 'DROP', 'ALTER', 'RENAME' => self::addressed($item, $scope, $keys, $words[0]),
            'CONVERT' => TableAlterations::convert($item, $scope),
            'ORDER' => TableAlterations::order($item, $scope),
            'DISABLE' => TableCommand::DisableKeys,
            'ENABLE' => TableCommand::EnableKeys,
            'FORCE' => TableCommand::Force,
            'UPGRADE' => TableCommand::UpgradePartitioning,
            default => throw new UnclassifiedSql('Unclassified MySQL table alteration: ' . Tree::text($item)),
        }];
    }

    /**
     * Binds an item whose second keyword selects between a column, an index or constraint, and the table.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function addressed(Node $item, Scope $scope, KeyAlterations $keys, string $verb): TableAlteration
    {
        $second = Tree::significant($item)[1] ?? null;
        $keyword = $second instanceof Token ? strtoupper($second->text) : '';
        $index = $second instanceof Node && $second->name === 'key_or_index';
        $renames = !in_array($verb, ['ADD', 'CHANGE', 'MODIFY', 'DROP', 'ALTER'], true);
        if ($renames && $index) {
            return $keys->rename($item, $scope);
        }
        if ($renames && $keyword === 'COLUMN') {
            return ColumnAlterations::rename($item, $scope);
        }
        return match ($verb) {
            'ADD' => (Tree::child($item, ['table_constraint_def', 'key_def']) !== null ? $keys->add($item, $scope) : ColumnAlterations::add($item, $scope, $keys)),
            'CHANGE', 'MODIFY' => ColumnAlterations::redeclare($item, $scope, $verb === 'CHANGE'),
            'DROP' => ($index || in_array($keyword, ['FOREIGN', 'PRIMARY', 'CHECK', 'CONSTRAINT'], true) ? $keys->drop($item, $scope) : ColumnAlterations::drop($item, $scope)),
            'ALTER' => (in_array($keyword, ['INDEX', 'CHECK', 'CONSTRAINT'], true) ? $keys->alter($item, $scope) : ColumnAlterations::alter($item, $scope)),
            default => TableAlterations::rename($item, $scope),
        };
    }
}
