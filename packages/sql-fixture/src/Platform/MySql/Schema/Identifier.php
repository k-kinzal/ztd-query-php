<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Syntax\NodeReader;
use SqlFixture\Syntax\QuotedText;
use SqlParser\Parser\Node;

/**
 * Reads the name an identifier node spells, without its quotes.
 *
 * @visibility root
 */
final class Identifier
{
    /**
     * Returns the identifier text, or null when the node holds no token.
     */
    public function decode(Node $ident): ?string
    {
        $token = (new NodeReader())->firstToken($ident);
        if ($token === null) {
            return null;
        }

        return $token->is('IDENT_QUOTED') ? (new QuotedText())->unquote($token->text) : $token->text;
    }
}
