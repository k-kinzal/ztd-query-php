<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Declaration;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A MySQL error number naming a condition, kept as written; the number 0 names no condition.
 * @visibility public
 * @example Reading a condition error number
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE duplicate CONDITION FOR 1062; END');
 *     $statement->body->declarations[0]->value->spelling // => '1062'
 *     $statement->body->declarations[0]->value->number() // => 1062
 */
final class ErrorCode
{
    /**
     * Requires a decimal or hexadecimal number whose value is not zero.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $spelling)
    {
        if (preg_match('/^(0[xX][0-9a-fA-F]+|[0-9]+(\.[0-9]*)?([eE][-+]?[0-9]+)?|\.[0-9]+([eE][-+]?[0-9]+)?)$/D', $spelling) !== 1 || $this->number() === 0) {
            throw new InvalidStructure('A condition error number is a nonzero unsigned number.');
        }
    }

    /**
     * Returns the error number the server reads from the spelling: the hexadecimal value or the leading integer digits.
     */
    public function number(): int
    {
        if (preg_match('/^0[xX]([0-9a-fA-F]+)$/D', $this->spelling, $hex) === 1) {
            return (int) hexdec($hex[1]);
        }
        return (int) preg_replace('/[^0-9].*$/Ds', '', $this->spelling);
    }
}
