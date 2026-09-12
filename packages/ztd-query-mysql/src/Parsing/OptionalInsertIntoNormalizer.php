<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parsing;

use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Token;

/**
 * Optional Insert Into Normalizer.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class OptionalInsertIntoNormalizer
{
    /**
     * Normalize Optional Insert Into for the supplied MySQL input.
     */
    public function normalizeOptionalInsertInto(string $sql): string
    {
        $tokens = [];
        foreach (Lexer::getTokens($sql)->tokens as $token) {
            if (in_array($token->type, [Token::TYPE_WHITESPACE, Token::TYPE_COMMENT, Token::TYPE_DELIMITER], true)) {
                continue;
            }
            $tokens[] = $token;
        }

        $insert = $tokens[0] ?? null;
        if ($insert === null || $insert->keyword !== 'INSERT') {
            return $sql;
        }

        $targetIndex = 1;
        while (isset($tokens[$targetIndex]) && in_array(
            $tokens[$targetIndex]->keyword,
            ['LOW_PRIORITY', 'DELAYED', 'HIGH_PRIORITY', 'IGNORE'],
            true,
        )) {
            $targetIndex++;
        }

        $target = $tokens[$targetIndex] ?? $insert;
        if (!in_array($target->type, [Token::TYPE_NONE, Token::TYPE_SYMBOL], true)) {
            return $sql;
        }
        if (!is_int($target->position)) {
            return $sql;
        }

        return substr($sql, 0, $target->position) . 'INTO ' . substr($sql, $target->position);
    }
}
