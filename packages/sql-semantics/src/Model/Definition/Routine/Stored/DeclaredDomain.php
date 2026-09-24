<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * The declared MySQL data type of a routine parameter, return value or local variable, with its optional collation.
 * @visibility public
 * @example Reading a declared parameter domain
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin) BEGIN END');
 *     $statement->parameters[0]->domain->collation // => 'utf8mb4_bin'
 */
final class DeclaredDomain
{
    /**
     * Requires a MySQL type and a nonempty collation name when one is declared.
     * @throws InvalidStructure
     */
    public function __construct(public readonly TypeDescriptor $type, public readonly ?string $collation = null)
    {
        if ($type->dialect !== Dialect::MySql || $collation === '') {
            throw new InvalidStructure('A stored program domain requires a MySQL type and a nonempty collation name.');
        }
    }
}
