<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Identity;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Sets one numeric attribute of an identity sequence to an integer literal.
 * @visibility public
 * @example Reading a new increment
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET INCREMENT BY 5');
 *     $statement->actions[0]->changes[0]->attribute // => \SqlSemantics\Model\Definition\Relation\Identity\SequenceAttribute::Increment
 *     $statement->actions[0]->changes[0]->value->text // => '5'
 */
final class SequenceValueChange
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly SequenceAttribute $attribute, public readonly Literal $value)
    {
        IdentityInvariant::integer($value);
    }
}
