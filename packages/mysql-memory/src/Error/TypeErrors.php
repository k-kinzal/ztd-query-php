<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize;

/**
 * Formats declared-type bounds and incompatible column attributes.
 *
 * Precision diagnostics retain the wording used by MySQL 5.6.
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

    /**
     * Formats a column attribute incompatible with its declared column kind.
     */
    public function columnAttribute(\SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute $diagnostic): SqlError
    {
        return match ($diagnostic->attribute) {
            'AUTO_INCREMENT' => SchemaError::WrongFieldSpec->error($diagnostic->column->value),
            'SRID' => StatementError::WrongUsage->error('SRID', 'non-geometry column'),
            'DEFAULT' => SchemaError::InvalidDefault->error($diagnostic->column->value),
            'ON UPDATE' => SchemaError::InvalidOnUpdate->error($diagnostic->column->value),
        };
    }


    /**
     * Bounds an unknown character-set name without changing the retained operand.
     */
    public function charset(string $name, bool $legacy = false): SqlError
    {
        return SchemaError::UnknownCharacterSet->error((new DiagnosticText())->bytes(explode("\0", $name, 2)[0], 64, $legacy));
    }

}
