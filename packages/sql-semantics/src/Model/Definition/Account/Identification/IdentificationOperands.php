<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Identification;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates credential operand categories without reading, hashing, or verifying any credential.
 * @visibility SqlSemantics
 */
final class IdentificationOperands
{
    /**
     * A cleartext credential is a MySQL text literal recorded as supplied.
     * @throws InvalidStructure
     */
    public static function secret(Literal $credential): void
    {
        if ($credential->type->dialect !== Dialect::MySql || $credential->literalKind !== LiteralKind::Text) {
            throw new InvalidStructure('An account credential requires a MySQL text literal.');
        }
    }

    /**
     * An authentication string may be written as text or as a hexadecimal number.
     * @throws InvalidStructure
     */
    public static function hash(Literal $hash): void
    {
        if ($hash->type->dialect !== Dialect::MySql || !in_array($hash->literalKind, [LiteralKind::Text, LiteralKind::Binary], true)) {
            throw new InvalidStructure('An authentication string requires a MySQL text or hexadecimal literal.');
        }
    }

    /**
     * An authentication plugin is identified by a nonempty name.
     * @throws InvalidStructure
     */
    public static function plugin(string $plugin): void
    {
        if ($plugin === '') {
            throw new InvalidStructure('An authentication plugin requires a nonempty name.');
        }
    }
}
