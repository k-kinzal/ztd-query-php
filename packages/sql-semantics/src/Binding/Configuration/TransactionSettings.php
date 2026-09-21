<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Setting;

/**
 * Retains isolation, access, and deferrability settings with their transaction lifetime.
 *
 * @visibility SqlSemantics
 */
final class TransactionSettings
{
    /**
     * @param list<Token> $tokens
     * @return list<Setting>
     */
    public function bind(array $tokens, Node $source, Scope $scope): array
    {
        $words = SettingTokens::words($tokens);
        $index = array_search('TRANSACTION', $words, true);
        if ($index === false) {
            return [];
        }
        $settingScope = in_array($words[0], ['SESSION', 'GLOBAL'], true) ? strtolower($words[0]) : ($scope->identifiers->dialect === Dialect::MySql ? 'next-transaction' : 'transaction');
        $result = [];
        foreach (SettingTokens::split(array_slice($tokens, $index + 1)) as $group) {
            $pending = $group;
            while ($pending !== []) {
                $values = SettingTokens::words($pending);
                $name = match ($values[0]) {
                    'ISOLATION' => 'transaction_isolation', 'READ' => 'transaction_access', default => 'transaction_deferrable'
                };
                $length = $values[0] === 'ISOLATION' ? (in_array($values[2] ?? '', ['READ', 'REPEATABLE'], true) ? 4 : 3) : ($values[0] === 'DEFERRABLE' ? 1 : 2);
                $result[] = (new SettingBinder())->make([$name], $settingScope, 'set', array_slice($pending, $values[0] === 'ISOLATION' ? 2 : 0, $values[0] === 'ISOLATION' ? $length - 2 : $length), $source, $scope);
                $pending = array_slice($pending, $length);
            }
        }
        return $result;
    }
}
