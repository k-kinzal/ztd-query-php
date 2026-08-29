<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Model;

/**
 * Extracts every terminal required by a compiled grammar resource.
 */
final class TerminalInventory
{
    /**
     * @return list<string>
     */
    public static function fromGrammar(Grammar $grammar): array
    {
        $terminals = [];
        foreach ($grammar->ruleMap as $rule) {
            foreach ($rule->alternatives as $production) {
                foreach ($production->symbols as $symbol) {
                    if ($symbol instanceof Terminal) {
                        $terminals[$symbol->value] = $symbol->value;
                    } elseif ($symbol instanceof NonTerminal && !isset($grammar->ruleMap[$symbol->value])) {
                        $terminals[$symbol->value] = $symbol->value;
                    }
                }
            }
        }

        $result = array_keys($terminals);
        sort($result);

        return $result;
    }
}
