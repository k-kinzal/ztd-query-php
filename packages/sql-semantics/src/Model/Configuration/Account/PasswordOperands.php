<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Account;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates the dialect and lexical credential operands without evaluating or verifying credentials.
 * @visibility SqlSemantics
 */
final class PasswordOperands
{
    /**
     * @throws InvalidStructure
     */
    public static function validate(Origin $origin, Literal ...$credentials): void
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('Account password requests require MySQL.');
        }
        foreach ($credentials as $credential) {
            if ($credential->type->dialect !== Dialect::MySql || $credential->literalKind !== LiteralKind::Text) {
                throw new InvalidStructure('An account credential requires a MySQL text literal.');
            }
        }
    }
}
