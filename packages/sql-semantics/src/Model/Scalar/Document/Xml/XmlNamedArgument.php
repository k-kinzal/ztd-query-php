<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document\Xml;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Scalar\Reference\Wildcard;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A value of XMLATTRIBUTES or XMLFOREST with the attribute or element name it produces: the alias, or else the name of the referenced column.
 * @visibility public
 * @example Naming a value by its alias or by its column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT XMLFOREST(n, n + 1 AS next) FROM t');
 *     [$query->outputs[0]->expression->elements[0]->alias, $query->outputs[0]->expression->elements[0]->label(), $query->outputs[0]->expression->elements[1]->label()] // => [null, 'n', 'next']
 * @example Rejecting an unnamed value that is not a column reference
 *     new \SqlSemantics\Model\Scalar\Document\Xml\XmlNamedArgument(\SqlSemantics\Model\Expression::literal(1, \SqlSemantics\Dialect::PostgreSql)); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class XmlNamedArgument
{
    /**
     * An unnamed value must reference a column or a whole row, whose name it takes.
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $value, public readonly ?string $alias = null)
    {
        if ($alias === '' || $alias === null && (!$value instanceof ColumnReference && !$value instanceof UnresolvedColumnReference && !$value instanceof Wildcard || $value->referenceParts() === [])) {
            throw new InvalidStructure('An XML attribute or element value is named by a nonempty alias or by the column it references.');
        }
    }

    /**
     * Returns the produced attribute or element name.
     */
    public function label(): string
    {
        $parts = $this->value->referenceParts();
        return $this->alias ?? (string) end($parts);
    }
}
