<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use InvalidArgumentException;
use SqlFaker\Contract\Grammar as ContractGrammar;
use SqlFaker\Contract\Production as ContractProduction;
use SqlFaker\Contract\ProductionRule as ContractProductionRule;
use SqlFaker\Contract\Symbol as ContractSymbol;

final class ContractGrammarProjector
{
    /**
     * @param class-string $nonTerminalClass
     */
    public static function project(object $grammar, string $nonTerminalClass): ContractGrammar
    {
        $properties = get_object_vars($grammar);
        $startSymbol = $properties['startSymbol'] ?? null;
        $ruleMap = $properties['ruleMap'] ?? null;

        if (!is_string($startSymbol) || $startSymbol === '') {
            throw new InvalidArgumentException('Grammar source must expose a non-empty startSymbol string.');
        }

        if (!is_array($ruleMap)) {
            throw new InvalidArgumentException('Grammar source must expose a ruleMap array.');
        }

        $rules = [];
        foreach ($ruleMap as $ruleName => $rule) {
            if (!is_string($ruleName) || !is_object($rule)) {
                throw new InvalidArgumentException('Grammar source ruleMap must be an object map keyed by strings.');
            }

            $ruleProperties = get_object_vars($rule);
            $alternatives = $ruleProperties['alternatives'] ?? null;
            if (!is_array($alternatives) || !array_is_list($alternatives)) {
                throw new InvalidArgumentException('Production rule source must expose an alternatives list.');
            }

            $productions = [];
            foreach ($alternatives as $alternative) {
                if (!is_object($alternative)) {
                    throw new InvalidArgumentException('Production rule alternatives must contain only objects.');
                }

                $alternativeProperties = get_object_vars($alternative);
                $symbols = $alternativeProperties['symbols'] ?? null;
                if (!is_array($symbols) || !array_is_list($symbols)) {
                    throw new InvalidArgumentException('Production source must expose a symbols list.');
                }

                $convertedSymbols = [];
                foreach ($symbols as $symbol) {
                    if (!is_object($symbol)) {
                        throw new InvalidArgumentException('Production symbols must contain only objects.');
                    }

                    $symbolProperties = get_object_vars($symbol);
                    $value = $symbolProperties['value'] ?? null;
                    if (!is_string($value) || $value === '') {
                        throw new InvalidArgumentException('Grammar symbol source must expose a non-empty value string.');
                    }

                    $convertedSymbols[] = new ContractSymbol($value, $symbol instanceof $nonTerminalClass);
                }

                $productions[] = new ContractProduction($convertedSymbols);
            }

            $rules[$ruleName] = new ContractProductionRule($ruleName, $productions);
        }

        return new ContractGrammar($startSymbol, $rules);
    }
}
