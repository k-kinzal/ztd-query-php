<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Relation;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Identity;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Schema\Column\IdentityMode;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes identity column additions, changes, and removals with their sequence options.
 * @visibility SqlSemantics
 */
final class IdentityActions
{
    /**
     * @return list<Tree> The action following the column name
     */
    public static function write(Identity\AddColumnIdentity|Identity\SetColumnIdentity|Identity\DropColumnIdentity $action): array
    {
        if ($action instanceof Identity\DropColumnIdentity) {
            return [Build::keyword('DROP IDENTITY' . ($action->ifExists ? ' IF EXISTS' : ''))];
        }
        if ($action instanceof Identity\AddColumnIdentity) {
            $options = array_map(self::option(...), $action->options);
            return [Build::keyword('ADD GENERATED ' . self::mode($action->mode) . ' AS IDENTITY'), ...($options === [] ? [] : [Build::parentheses(new Tree('sequence-options', $options))])];
        }
        $changes = [];
        foreach ($action->changes as $change) {
            $changes[] = match (true) {
                $change instanceof IdentityMode => Build::keyword('SET GENERATED ' . self::mode($change)),
                $change instanceof Identity\RestartIdentity => self::option($change),
                default => new Tree('set-option', [Build::keyword('SET'), self::option($change)]),
            };
        }
        return $changes;
    }

    /**
     * Spells the identity mode.
     */
    public static function mode(IdentityMode $mode): string
    {
        return $mode === IdentityMode::Always ? 'ALWAYS' : 'BY DEFAULT';
    }

    /**
     * Writes one sequence option.
     */
    public static function option(Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SetSequenceName|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity $option): Tree
    {
        $dialect = Dialect::PostgreSql;
        return new Tree('sequence-option', match (true) {
            $option instanceof Identity\SequenceValueChange => [Build::keyword($option->attribute->value), Expressions::write($option->value)],
            $option instanceof Identity\SequenceFlag => [Build::keyword($option->value)],
            $option instanceof Identity\SetSequenceName => [Build::keyword('SEQUENCE NAME'), Build::identifier($option->name->parts, $dialect)],
            $option instanceof Identity\SequenceStorage => [Build::keyword('AS'), TypeDeclaration::write($option->type)],
            $option instanceof Identity\SetSequenceOwner => [Build::keyword('OWNED BY'), $option->column === null ? Build::keyword('NONE') : Build::identifier($option->column->parts, $dialect)],
            $option instanceof Identity\RestartIdentity => [Build::keyword('RESTART'), ...($option->value === null ? [] : [Build::keyword('WITH'), Expressions::write($option->value)])],
        });
    }
}
