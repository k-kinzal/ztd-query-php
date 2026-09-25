<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * The declared MySQL data type of a routine parameter, return value or local variable, with its optional collation
 * and whether an unsigned numeric type is displayed ZEROFILL (padded with leading zeros).
 * @visibility public
 * @example Reading a declared parameter domain
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin) BEGIN END');
 *     $statement->parameters[0]->domain->collation // => 'utf8mb4_bin'
 */
final class DeclaredDomain
{
    /**
     * Requires a MySQL type, a nonempty collation name when one is declared, and an unsigned numeric type for
     * ZEROFILL, which implies UNSIGNED.
     * @throws InvalidStructure
     */
    public function __construct(public readonly TypeDescriptor $type, public readonly ?string $collation = null, public readonly bool $zeroFill = false)
    {
        if ($type->dialect !== Dialect::MySql || $collation === '') {
            throw new InvalidStructure('A stored program domain requires a MySQL type and a nonempty collation name.');
        }
        $identity = $type->identity;
        if ($zeroFill && !(($identity instanceof \SqlSemantics\Type\Identity\Numeric\IntegerStorage || $identity instanceof \SqlSemantics\Type\Identity\Numeric\NumericStorage) && $identity->unsigned)) {
            throw new InvalidStructure('ZEROFILL applies to an unsigned numeric type.');
        }
    }
}
