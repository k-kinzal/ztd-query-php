<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Write;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Write\DefaultSource;
use SqlSemantics\Serialization\Expressions;

/**
 * Serializes a write slot from its expression or destination-default instruction.
 * @visibility SqlSemantics
 */
final class Inputs
{
    /**
     * Emits DEFAULT only through a storage-input serializer.
     */
    public static function write(Expression|DefaultSource $value): Tree
    {
        return $value instanceof DefaultSource ? Build::keyword('DEFAULT') : Expressions::write($value);
    }
}
