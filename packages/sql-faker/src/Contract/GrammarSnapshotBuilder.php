<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

use InvalidArgumentException;

/**
 * Normalizes one compiled supported grammar into the public contract snapshot.
 */
final class GrammarSnapshotBuilder
{
    /**
     * @param list<string> $entryRules
     * @param list<FamilyDefinition> $families
     * @param class-string $nonTerminalClass
     */
    public function build(
        string $dialect,
        object $grammar,
        array $entryRules,
        array $families,
        string $nonTerminalClass,
    ): GrammarSnapshot {
        $startRule = $this->readStringProperty($grammar, 'startSymbol');
        $ruleMap = $this->readObjectMapProperty($grammar, 'ruleMap');

        $rules = [];
        foreach ($ruleMap as $ruleName => $rule) {
            $alternatives = [];
            foreach ($this->readObjectListProperty($rule, 'alternatives', 'Production rule must expose an alternatives array.') as $alternative) {
                $symbols = [];
                foreach ($this->readObjectListProperty($alternative, 'symbols', 'Production must expose a symbols array.') as $symbol) {
                    $symbols[] = new GrammarSymbolSnapshot(
                        $this->readStringProperty($symbol, 'value'),
                        $symbol instanceof $nonTerminalClass,
                    );
                }
                $alternatives[] = new GrammarAlternativeSnapshot($symbols);
            }

            $rules[$ruleName] = new GrammarRuleSnapshot($ruleName, $alternatives);
        }

        $familyAnchors = [];
        foreach ($families as $family) {
            $familyAnchors[$family->id] = $family->anchorRules;
        }

        return new GrammarSnapshot(
            $dialect,
            $startRule,
            $entryRules,
            $rules,
            $familyAnchors,
        );
    }

    private function readStringProperty(object $object, string $property): string
    {
        $properties = get_object_vars($object);
        $value = $properties[$property] ?? null;
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('Object property %s must be a string.', $property));
        }

        return $value;
    }

    /**
     * @return array<string, object>
     */
    private function readObjectMapProperty(object $object, string $property): array
    {
        $properties = get_object_vars($object);
        $value = $properties[$property] ?? null;
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Object property %s must be an array.', $property));
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || !is_object($item)) {
                throw new InvalidArgumentException(sprintf('Object property %s must be an object map.', $property));
            }
            $map[$key] = $item;
        }

        return $map;
    }

    /**
     * @return list<object>
     */
    private function readObjectListProperty(object $object, string $property, string $message): array
    {
        $properties = get_object_vars($object);
        $value = $properties[$property] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException($message);
        }

        $list = [];
        foreach ($value as $item) {
            if (!is_object($item)) {
                throw new InvalidArgumentException($message);
            }
            $list[] = $item;
        }

        return $list;
    }
}
