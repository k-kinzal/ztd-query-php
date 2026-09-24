<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Schema\Column\SequenceOptions;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes explicitly declared sequence attributes.
 *
 * @visibility SqlSemantics
 */
final class Sequence
{
    /**
     * Leaves omitted attributes to database defaults.
     */
    public static function write(SequenceOptions $options): Tree
    {
        $dialect = \SqlSemantics\Dialect::PostgreSql;
        $parts = [];
        if ($options->name !== null) {
            array_push($parts, Build::keyword('SEQUENCE NAME'), Build::identifier($options->name->parts, $dialect));
        }
        if ($options->storage !== null) {
            array_push($parts, Build::keyword('AS'), \SqlSemantics\Serialization\TypeDeclaration::write($options->storage));
        }
        foreach (['START WITH' => $options->start, 'INCREMENT BY' => $options->increment, 'MINVALUE' => $options->minimum, 'MAXVALUE' => $options->maximum, 'CACHE' => $options->cache] as $keyword => $value) {
            if ($value !== null) {
                array_push($parts, Build::keyword($keyword), Expressions::write($value));
            }
        }
        if ($options->cycle !== null) {
            $parts[] = Build::keyword($options->cycle ? 'CYCLE' : 'NO CYCLE');
        }
        if ($options->logged !== null) {
            $parts[] = Build::keyword($options->logged ? 'LOGGED' : 'UNLOGGED');
        }
        if ($options->owner !== null) {
            array_push($parts, Build::keyword('OWNED BY'), $options->owner->column === null ? Build::keyword('NONE') : Build::identifier($options->owner->column->parts, $dialect));
        }
        if ($options->restart !== null) {
            array_push($parts, Build::keyword('RESTART'), ...($options->restart->value === null ? [] : [Build::keyword('WITH'), Expressions::write($options->restart->value)]));
        }
        return $parts === [] ? new Tree('sequence', []) : Build::parentheses(new Tree('sequence', $parts));
    }
}
