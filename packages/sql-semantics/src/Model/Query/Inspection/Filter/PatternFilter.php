<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Filter;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A LIKE pattern restricting a metadata listing to matching names.
 * @visibility public
 * @example Inspecting a LIKE filter
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SHOW DATABASES LIKE 'app%'");
 *     $statement->filter->pattern->text // => "'app%'"
 */
final class PatternFilter
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $pattern)
    {
        if ($pattern->type->dialect !== Dialect::MySql || $pattern->literalKind !== LiteralKind::Text) {
            throw new InvalidStructure('A metadata pattern requires a MySQL text literal.');
        }
    }
}
