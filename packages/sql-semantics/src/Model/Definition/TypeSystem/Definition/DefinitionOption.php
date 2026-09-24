<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Definition;

use SqlSemantics\Model\Definition\Routine\ColumnTypeReference;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * One attribute of a definition list with its typed argument; a null argument resets the attribute where ALTER allows NONE.
 * @visibility public
 * @example Reading a typed operator attribute
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR === (FUNCTION = int4eq, LEFTARG = integer, RIGHTARG = integer, HASHES)');
 *     $statement->options[1]->value->name // => 'integer'
 *     $statement->options[3]->value // => true
 * @example Rejecting an argument of the wrong form
 *     new \SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption(\SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute::Hashes, 'yes'); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DefinitionOption
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly DefinitionAttribute $attribute, public readonly QualifiedName|TypeDescriptor|ColumnTypeReference|bool|int|string|null $value)
    {
        if ($value === null) {
            return;
        }
        $kind = $attribute->kind();
        if (!$kind->accepts($value) || (is_string($value) && $kind === DefinitionKind::Choice && $attribute->choose($value) !== $value)) {
            throw new InvalidStructure('The ' . $attribute->spelling() . ' attribute requires a ' . $kind->value . ' argument.');
        }
    }
}
