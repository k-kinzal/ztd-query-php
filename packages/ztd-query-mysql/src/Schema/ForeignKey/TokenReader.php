<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Schema\ForeignKey;

use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;

/**
 * Token Reader.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class TokenReader
{
    /**
     * Is Symbol for the supplied MySQL input.
     */
    public static function isSymbol(?SqlToken $token, string $symbol): bool
    {
        if ($token === null) {
            return false;
        }

        return $token->kind === SqlTokenKind::Symbol && $token->text === $symbol;
    }
}
