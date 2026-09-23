<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Foreign;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\FunctionChange;
use SqlSemantics\Model\Definition\Foreign\SetForeignOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes support-function operations and the closed set of foreign option changes.
 * @visibility SqlSemantics
 */
final class WrapperOptions
{
    /**
     * @return list<Tree> Omission, explicit removal, or a named function
     */
    public static function support(QualifiedName|FunctionChange|null $function, bool $handler): array
    {
        if ($function === FunctionChange::Keep) {
            return [];
        }
        $kind = $handler ? 'HANDLER' : 'VALIDATOR';
        return $function instanceof QualifiedName ? [Build::keyword($kind), Build::identifier($function->parts, Dialect::PostgreSql)] : [Build::keyword('NO ' . $kind)];
    }

    /**
     * Writes initial text-valued options with identifier and literal boundaries.
     */
    public static function option(ForeignOption $option): Tree
    {
        return new Tree('foreign-option', [Build::identifier([$option->name], Dialect::PostgreSql), Expressions::write($option->value)]);
    }

    /**
     * Keeps deletion separate from the two value-bearing changes.
     */
    public static function change(AddForeignOption|SetForeignOption|DropForeignOption $change): Tree
    {
        if ($change instanceof DropForeignOption) {
            return new Tree('drop-option', [Build::keyword('DROP'), Build::identifier([$change->name], Dialect::PostgreSql)]);
        }
        return new Tree('write-option', [Build::keyword($change instanceof AddForeignOption ? 'ADD' : 'SET'), self::option($change->option)]);
    }
}
