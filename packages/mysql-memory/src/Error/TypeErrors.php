<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

use MySqlMemory\Error\Family\SchemaError;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize;

/**
 * Formats declared-type bounds, including the precision wording used by MySQL 5.6.
 *
 * @visibility MySqlMemory
 */
final class TypeErrors
{
    /**
     * Answers the error for a declared type's length, precision or scale.
     */
    public function size(InvalidTypeSize $diagnostic, bool $legacy = false): SqlError
    {
        return new SqlError(match ($diagnostic->rule) {
            \SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Width => SchemaError::TooBigDisplayWidth,
            \SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Length => SchemaError::TooBigFieldLength,
            \SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Precision => SchemaError::TooBigPrecision,
            \SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Scale => SchemaError::TooBigScale,
            \SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::ScaleExceedsPrecision => SchemaError::MBiggerThanD,
            \SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Empty => SchemaError::InvalidFieldSize,
            \SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Specifier => SchemaError::WrongFieldSpec,
        }, $legacy && $diagnostic->rule === \SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Precision
            ? sprintf("Too big precision %d specified for column '%s'. Maximum is %d.", $diagnostic->actual, $diagnostic->name, $diagnostic->maximum)
            : $diagnostic->message());
    }
}
