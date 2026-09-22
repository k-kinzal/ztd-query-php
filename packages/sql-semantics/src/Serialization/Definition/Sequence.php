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
        $parts = [];
        foreach (['START WITH' => $options->start, 'INCREMENT BY' => $options->increment, 'MINVALUE' => $options->minimum, 'MAXVALUE' => $options->maximum, 'CACHE' => $options->cache] as $keyword => $value) {
            if ($value !== null) {
                array_push($parts, Build::keyword($keyword), Expressions::write($value));
            }
        }
        if ($options->cycle !== null) {
            $parts[] = Build::keyword($options->cycle ? 'CYCLE' : 'NO CYCLE');
        }
        return $parts === [] ? new Tree('sequence', []) : Build::parentheses(new Tree('sequence', $parts));
    }
}
