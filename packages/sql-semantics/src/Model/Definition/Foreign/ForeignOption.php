<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Foreign;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One foreign-data wrapper option: an identifier and a required text-literal operand.
 * @visibility public
 * @example Inspecting wrapper-specific text options
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app OPTIONS (import_default 'true')");
 *     $statement->options[0]->name // => 'import_default'
 */
final class ForeignOption
{
    /**
     * The selected wrapper defines option names and interprets their text values.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly Literal $value)
    {
        if ($name === '' || $value->type->dialect !== Dialect::PostgreSql || $value->literalKind !== LiteralKind::Text) {
            throw new InvalidStructure('A foreign-data wrapper option requires a name and a PostgreSQL text literal.');
        }
    }
}
