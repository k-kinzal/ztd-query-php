<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

use SqlFaker\Contract\Grammar as ContractGrammar;

final class ContractGrammarHydrator
{
    public static function hydrate(ContractGrammar $grammar): Grammar
    {
        $rules = [];
        foreach ($grammar->rules as $ruleName => $rule) {
            $alternatives = [];
            foreach ($rule->alternatives as $alternative) {
                $symbols = [];
                foreach ($alternative->symbols as $symbol) {
                    $symbols[] = $symbol->isNonTerminal
                        ? new NonTerminal($symbol->name)
                        : new Terminal($symbol->name);
                }

                $alternatives[] = new Production($symbols);
            }

            $rules[$ruleName] = new ProductionRule($ruleName, $alternatives);
        }

        return new Grammar($grammar->startSymbol, $rules);
    }
}
