<?php

declare(strict_types=1);

namespace LemonParser\Printer;

use LemonParser\Ast\RhsItem;
use LemonParser\Ast\Rule;
use LemonParser\Ast\Symbol;

/**
 * Writes rules back as Lemon reads them.
 *
 * @visibility root
 */
final class RulePrinter
{
    /**
     * Writes one rule on one line, without the line break.
     *
     * @param Rule $rule The rule
     *
     * @return string `lhs(alias) ::= items. [PREC] {NEVER-REDUCE} {code}`
     */
    public function print(Rule $rule): string
    {
        $text = $rule->lhs->name . ($rule->lhsAlias === null ? '' : "({$rule->lhsAlias})") . ' ::=';
        foreach ($rule->items as $item) {
            $text .= ' ' . $this->item($item);
        }
        $text .= '.';
        if ($rule->precedence !== null) {
            $text .= " [{$rule->precedence->name}]";
        }
        if ($rule->neverReduce) {
            $text .= ' {NEVER-REDUCE}';
        }
        if ($rule->code !== null) {
            $text .= ' {' . $rule->code->code . '}';
        }

        return $text;
    }

    /**
     * Writes one position of a right-hand side.
     *
     * @param RhsItem $item The position
     *
     * @return string The symbols joined by `|`, then the alias in parentheses
     */
    public function item(RhsItem $item): string
    {
        $names = array_map(static fn (Symbol $symbol): string => $symbol->name, $item->symbols);

        return implode('|', $names) . ($item->alias === null ? '' : "({$item->alias})");
    }
}
