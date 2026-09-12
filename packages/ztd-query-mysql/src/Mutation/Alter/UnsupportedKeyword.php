<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation\Alter;

use PhpMyAdmin\SqlParser\Components\AlterOperation;

/**
 * Unsupported Keyword.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class UnsupportedKeyword
{
    /**
     * Has Unsupported Keyword In Unknown for the supplied MySQL input.
     */
    public function hasUnsupportedKeywordInUnknown(AlterOperation $op): bool
    {
        $unsupportedPatterns = [
            'SPATIAL INDEX',
            'SPATIAL KEY',
            'PARTITION',
        ];

        $unknownTokens = is_array($op->unknown) ? $op->unknown : [];
        foreach ($unknownTokens as $token) {
            $tokenValue = is_string($token->value) ? $token->value : '';
            $value = strtoupper($tokenValue);
            foreach ($unsupportedPatterns as $pattern) {
                if (str_contains($value, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }
}
