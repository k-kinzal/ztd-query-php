<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parsing\Alter;

use PhpMyAdmin\SqlParser\Components\AlterOperation;
use PhpMyAdmin\SqlParser\Components\OptionsArray;

/**
 * Reads ALTER options and unparsed keywords using the parser's option semantics.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class OptionList
{
    /**
     * @param list<string> $keywords
     */
    public static function hasAny(OptionsArray $options, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($options->has($keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return list<string>
     */
    public static function unknownKeywords(AlterOperation $operation): array
    {
        $keywords = [];
        foreach (is_array($operation->unknown) ? $operation->unknown : [] as $token) {
            $keywords[] = is_string($token->value) ? strtoupper($token->value) : '';
        }
        return $keywords;
    }
}
