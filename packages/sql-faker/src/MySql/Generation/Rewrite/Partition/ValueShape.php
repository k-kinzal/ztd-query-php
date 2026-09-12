<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Partition;

use SqlFaker\Generation\Token\TerminalOccurrence;

/**
 * Models the scalar and row value lists of sql_yacc.yy:part_values_in and part_func_max.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class ValueShape
{
    /**
     * Splits only top-level commas, keeping nested expressions intact.
     * @param list<TerminalOccurrence> $tokens
     * @return list<list<TerminalOccurrence>>
     */
    public function split(array $tokens): array
    {
        $items = [];
        $item = [];
        $depth = 0;
        foreach ($tokens as $token) {
            if ($token->name === ',' && $depth === 0) {
                $items[] = $item;
                $item = [];
                continue;
            }
            $item[] = $token;
            $depth += match ($token->name) {
                '(' => 1, ')' => -1, default => 0
            };
        }
        if ($item !== []) {
            $items[] = $item;
        }
        return $items;
    }

    /**
     * Reads row syntax after ListValueRule has prefixed parenthesized scalar expressions with plus.
     * @param list<TerminalOccurrence> $tokens
     * @return list<list<list<TerminalOccurrence>>>
     */
    public function rows(array $tokens, bool $list): array
    {
        if (($tokens[0]->name ?? null) === 'MAX_VALUE_SYM') {
            return [[$tokens]];
        }
        $items = $this->split(array_slice($tokens, 1, -1));
        if ($list && ($tokens[1]->name ?? null) === '(') {
            return array_map(fn (array $row): array => $this->split(array_slice($row, 1, -1)), $items);
        }
        return $list ? array_map(static fn (array $item): array => [$item], $items) : [$items];
    }

    /**
     * Retains existing expressions and supplies missing fields; row syntax requires at least two columns.
     * @param list<TerminalOccurrence> $tokens
     * @return list<TerminalOccurrence>
     */
    public function resize(array $tokens, bool $list, int $width): array
    {
        if (!$list && $width === 1 && ($tokens[0]->name ?? null) === 'MAX_VALUE_SYM') {
            return $tokens;
        }
        $rows = $this->rows($tokens, $list);
        if ($list && $width === 1) {
            return $this->join(array_merge(...$rows), true);
        }
        $output = [];
        foreach ($rows as $row) {
            $row = array_slice($row, 0, $width);
            while (count($row) < $width) {
                $row[] = [$this->token(($tokens[0]->name ?? null) === 'MAX_VALUE_SYM' ? 'MAX_VALUE_SYM' : 'NUM')];
            }
            $output[] = $this->join($row, true);
        }
        return $this->join($output, $list);
    }

    /**
     * New punctuation is assigned real occurrence IDs when ValueArityRule installs the replacement.
     * @param list<list<TerminalOccurrence>> $items
     * @return list<TerminalOccurrence>
     */
    public function join(array $items, bool $parenthesized): array
    {
        $tokens = $parenthesized ? [$this->token('(')] : [];
        foreach ($items as $index => $item) {
            if ($index > 0) {
                $tokens[] = $this->token(',');
            }
            array_push($tokens, ...$item);
        }
        if ($parenthesized) {
            $tokens[] = $this->token(')');
        }
        return $tokens;
    }

    /**
     * Marks temporary punctuation without borrowing an original derivation's identity.
     */
    public function token(string $name): TerminalOccurrence
    {
        return new TerminalOccurrence($name, PHP_INT_MIN);
    }
}
