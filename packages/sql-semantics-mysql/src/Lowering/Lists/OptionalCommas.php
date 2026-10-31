<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Lists;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;

/**
 * Retains the optional boundaries of a previously validated grammar list.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class OptionalCommas
{
    /**
     * @param Lowering $lowering The lowering that validates optional words
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Reads boundaries without descending into an operand's own lists.
     *
     * @param list<string> $items The grammar rules that hold list operands
     * @return list<bool>
     */
    public function between(Node $list, array $items): array
    {
        $pending = [$list];
        $commas = [];
        $first = true;
        $comma = false;
        while ($pending !== []) {
            $part = array_pop($pending);
            if ($part instanceof Token) {
                $comma = $comma || $part->text === ',';
            } elseif (in_array($part->name, $items, true)) {
                if (!$first) {
                    $commas[] = $comma;
                }
                $first = false;
                $comma = false;
            } elseif ($part->name === 'opt_comma') {
                $comma = $this->lowering->options->present($part);
            } else {
                array_push($pending, ...array_reverse($part->children));
            }
        }
        return $commas;
    }
}
