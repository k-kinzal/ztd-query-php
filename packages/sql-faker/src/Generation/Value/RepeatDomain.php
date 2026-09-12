<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Value;

use Closure;
use InvalidArgumentException;
use Override;

/**
 * Repeats complete compound components, with a bounded sampling count.
 */
final class RepeatDomain implements ValueDomain
{
    /**
     * @throws InvalidArgumentException When sampling bounds cannot form a repetition
     */
    public function __construct(private readonly ValueDomain $element, private readonly int $minimum, private readonly int $maximum)
    {
        if ($minimum < 0 || $maximum < $minimum || $maximum > 65535) {
            throw new InvalidArgumentException('Require ordered repetition bounds between zero and 65535.');
        }
    }

    /**
     * @param Closure(positive-int): int $choose
     */
    #[Override]
    public function choose(Closure $choose): string
    {
        $count = $this->minimum + $choose(max(1, $this->maximum - $this->minimum + 1));
        $value = '';
        for ($index = 0; $index < $count; ++$index) {
            $value .= $this->element->choose($choose);
        }
        return $value;
    }

    /**
     * @return list<int>
     */
    #[Override]
    public function match(string $value, int $offset = 0): array
    {
        $positions = [$offset];
        $ends = [];
        for ($count = 0; $positions !== []; ++$count) {
            if ($count >= $this->minimum) {
                $ends = [...$ends, ...$positions];
            }
            $next = [];
            foreach ($positions as $position) {
                foreach ($this->element->match($value, $position) as $end) {
                    if ($end > $position || $count < $this->minimum) {
                        $next[] = $end;
                    }
                }
            }
            $positions = array_values(array_unique($next));
        }
        return array_values(array_unique($ends));
    }
}
