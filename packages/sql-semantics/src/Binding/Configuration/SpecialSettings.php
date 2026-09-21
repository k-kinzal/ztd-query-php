<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Configuration\Setting;

/**
 * Names SQL's special setting forms without treating their values as column names.
 *
 * @visibility SqlSemantics
 */
final class SpecialSettings
{
    /**
     * @param list<Token> $tokens
     * @return list<Setting>
     */
    public function bind(array $tokens, string $settingScope, Node $source, Scope $scope): array
    {
        $words = SettingTokens::words($tokens);
        $prefix = implode(' ', array_slice($words, 0, 2));
        $length = in_array($prefix, ['TIME ZONE', 'SESSION AUTHORIZATION', 'CHARACTER SET', 'XML OPTION'], true) ? 2 : 1;
        $name = strtolower(implode(' ', array_slice($words, 0, $length)));
        $name = match ($name) {
            'time zone' => 'timezone', 'schema' => 'search_path', 'names' => 'names', 'session authorization' => 'session_authorization', 'xml option' => 'xmloption', default => $name,
        };
        $values = array_slice($tokens, $length);
        $collation = array_search('COLLATE', SettingTokens::words($values), true);
        $binder = new SettingBinder();
        $result = [$binder->make([$name], $settingScope, 'set', $collation === false ? $values : array_slice($values, 0, $collation), $source, $scope)];
        if ($collation !== false) {
            $result[] = $binder->make(['collation_connection'], $settingScope, 'set', array_slice($values, $collation + 1), $source, $scope);
        }
        return $result;
    }
}
