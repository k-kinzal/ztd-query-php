<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Definition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\ColumnTypeReference;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Type\TypeDescriptor;

/**
 * The form of argument a definition attribute takes: a routine or object name, an operator, a type, a Boolean, an integer, a type length, free text, or one of fixed keywords.
 * @visibility public
 * @example Reading the argument form of an operator attribute
 *     \SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute::LeftArg->kind() // => \SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind::Type
 *     \SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind::Boolean->accepts(true) // => true
 */
enum DefinitionKind: string
{
    case Name = 'name';
    case Operator = 'operator';
    case Type = 'type';
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Length = 'length';
    case Text = 'text';
    case Choice = 'choice';

    /**
     * Operator symbols consist of the characters PostgreSQL allows in operator names.
     */
    public const SYMBOL = '/^[~!@#^&|`?+\-*\/%<>=]+$/D';

    /**
     * Whether a value has the PHP form of this argument kind; a type length is -2, -1, or 1 to 32767.
     */
    public function accepts(QualifiedName|TypeDescriptor|ColumnTypeReference|bool|int|string $value): bool
    {
        return match ($this) {
            self::Name => $value instanceof QualifiedName,
            self::Operator => $value instanceof QualifiedName && preg_match(self::SYMBOL, $value->parts[count($value->parts) - 1]) === 1 && count($value->parts) <= 2,
            self::Type => $value instanceof ColumnTypeReference || ($value instanceof TypeDescriptor && $value->dialect === Dialect::PostgreSql),
            self::Boolean => is_bool($value),
            self::Integer => is_int($value),
            self::Length => is_int($value) && ($value === -2 || $value === -1 || ($value >= 1 && $value <= 32767)),
            self::Text, self::Choice => is_string($value),
        };
    }
}
