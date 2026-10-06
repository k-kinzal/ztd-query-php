<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Enumeration;

use Deriver\Evaluation\Candidate\Choices;
use Deriver\Value\Term;

/**
 * Enumerates a shared choice DAG while retaining its entire unvisited frontier.
 * @visibility root
 */
final class Cursor
{
    /**
     * @var list<array{Term, array<string, bool|string>}>
     */
    private array $pending;

    /**
     * Starts enumeration without flattening any choices.
     */
    public function __construct(Term $value)
    {
        $this->pending = [[$value, []]];
    }

    /**

     * @return array{Term, array<string, bool|string>}|null

     */
    public function next(): ?array
    {
        while ($this->pending !== []) {
            [$value, $guard] = array_pop($this->pending);
            if ($value->kind !== 'choice') {
                return [$value, $guard];
            }
            foreach (array_reverse($value->operands) as $item) {
                $conditions = [];
                foreach ($item->attributes as $key => $selection) {
                    if (str_starts_with($key, 'guard:') && (is_bool($selection) || is_string($selection))) {
                        $conditions[substr($key, 6)] = $selection;
                    }
                }
                $merged = (new Choices())->merge($guard, $conditions);
                if ($merged !== null) {
                    $this->pending[] = [$item->operands[0], $merged];
                }
            }
        }
        return null;
    }

    /**
     * Reports whether an unvisited frontier remains.
     */
    public function hasRemaining(): bool
    {
        return $this->pending !== [];
    }

    /**

     * @param array{Term, array<string, bool|string>} $current

     */
    public function remainder(array $current): Term
    {
        return (new Choices())->make([$current, ...array_reverse($this->pending)]);
    }
}
