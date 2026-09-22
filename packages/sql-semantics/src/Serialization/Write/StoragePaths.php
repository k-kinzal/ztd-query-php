<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Write;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Storage;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes writable paths without treating them as computed expression results.
 *
 * @visibility SqlSemantics
 */
final class StoragePaths
{
    /**
     * Writes only the location forms permitted by a storage path.
     * @throws InvalidStructure
     */
    public static function write(Storage\Path $path): Tree
    {
        return match (true) {
            $path instanceof Storage\ColumnPath => Expressions::write($path->reference),
            $path instanceof Storage\FieldPath => new Tree('field-target', [self::write($path->base), Build::keyword('.'), Build::identifier([$path->field], $path->type()->dialect)]),
            $path instanceof Storage\ElementPath => new Tree('element-target', [self::write($path->base), Build::keyword('['), Expressions::write($path->index), Build::keyword(']')]),
            $path instanceof Storage\SlicePath => new Tree('slice-target', [self::write($path->base), Build::keyword('['), ...($path->lower === null ? [] : [Expressions::write($path->lower)]), Build::keyword(':'), ...($path->upper === null ? [] : [Expressions::write($path->upper)]), Build::keyword(']')]),
            default => throw new InvalidStructure('Unclassified storage location.'),
        };
    }
}
