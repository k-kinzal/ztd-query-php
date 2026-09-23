<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration\Role;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Validation\Collections;

/**
 * Reads lexical account names without treating names or host qualifiers as value expressions.
 * @visibility SqlSemantics
 */
final class AccountNames
{
    /**
     * @return non-empty-list<AccountName>
     */
    public static function read(Node $list, Identifiers $identifiers): array
    {
        $roles = [];
        foreach (Tree::outer($list, ['role']) as $role) {
            $tokens = $role->tokens();
            $roles[] = new AccountName(self::part($tokens[0], $identifiers), isset($tokens[2]) ? self::part($tokens[2], $identifiers) : null);
        }
        return Collections::nonEmpty($roles);
    }

    /**
     * Decodes MySQL string quoting when a name uses a string token, preserving identifier case.
     */
    public static function part(Token $token, Identifiers $identifiers): string
    {
        return \SqlSemantics\Ast\MySqlNames::read($token, $identifiers);
    }
}
