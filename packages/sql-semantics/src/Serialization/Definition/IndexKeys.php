<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Schema\Index;
use SqlSemantics\Schema\IndexElement;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes typed index values and their ordered access modifiers.
 *
 * @visibility SqlSemantics
 */
final class IndexKeys
{
    /**
     * Column names remain unqualified within a declaration.
     */
    public static function write(IndexElement $key, Dialect $dialect): Tree
    {
        $parts = [$key instanceof Index\ColumnKey ? Build::identifier([$key->column->columnBinding()?->column->name ?? $key->column->referenceParts()[0]], $dialect) : Build::parentheses(Expressions::write($key->value()))];
        if ($key instanceof Index\ColumnKey && $key->prefixLength !== null) {
            $parts[] = Build::parentheses(Build::keyword((string) $key->prefixLength));
        }
        if ($key->collation !== null) {
            array_push($parts, Build::keyword('COLLATE'), Build::identifier($key->collation->parts, $dialect));
        }
        if ($key->operatorClass !== null) {
            $parts[] = Build::identifier($key->operatorClass->parts, $dialect);
            if ($key->operatorParameters !== []) {
                $parts[] = Build::parentheses(Storage::parameters($key->operatorParameters, $dialect));
            }
        }
        if ($key->direction !== null) {
            $parts[] = Build::keyword($key->direction->value);
        }
        if ($key->nulls !== null) {
            $parts[] = Build::keyword('NULLS ' . $key->nulls->value);
        }
        return new Tree('index-key', $parts);
    }
}
