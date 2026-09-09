<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Value;

use Closure;
use InvalidArgumentException;
use LogicException;
use Override;

/**
 * Constructs bounded unsigned decimals by prefix intervals, independent of native integer width.
 */
final class IntegerDomain implements ValueDomain
{
    /**
     * @var positive-int
     */
    private readonly int $widthChoices;
    /**
     * @var positive-int
     */
    private readonly int $paddingChoices;

    /**
     * Binds normalized inclusive magnitudes; leading zeroes are spelling choices, not magnitude changes.
     * @throws InvalidArgumentException When magnitudes or padding bounds are invalid
     */
    public function __construct(private readonly string $minimum, private readonly string $maximum, private readonly int $maximumLeadingZeroes = 16)
    {
        $widthChoices = strlen($maximum) - strlen($minimum) + 1;
        $paddingChoices = $maximumLeadingZeroes + 1;
        if ($paddingChoices < 1 || $paddingChoices > 1025 || $widthChoices < 1 || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $minimum) !== 1
            || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $maximum) !== 1
            || strlen($minimum) > strlen($maximum)
            || (strlen($minimum) === strlen($maximum) && strcmp($minimum, $maximum) > 0)) {
            throw new InvalidArgumentException('Require normalized unsigned decimal bounds in ascending order.');
        }
        $this->widthChoices = $widthChoices;
        $this->paddingChoices = $paddingChoices;
    }

    /**
     * Every magnitude in the interval is reachable; no sample rejection or domain enumeration is needed.
     * @param Closure(positive-int): int $choose
     * @throws LogicException When a caller decision makes a decimal prefix impossible
     */
    #[Override]
    public function choose(Closure $choose): string
    {
        $length = strlen($this->minimum) + $choose($this->widthChoices);
        $lower = $length === strlen($this->minimum) ? $this->minimum : '1' . str_repeat('0', $length - 1);
        $upper = $length === strlen($this->maximum) ? $this->maximum : str_repeat('9', $length);
        $value = '';
        for ($index = 0; $index < $length; ++$index) {
            $low = $value === substr($lower, 0, $index) ? (int) $lower[$index] : 0;
            $high = $value === substr($upper, 0, $index) ? (int) $upper[$index] : 9;
            $count = $high - $low + 1;
            if ($count < 1) {
                throw new LogicException('No digit completes the selected decimal prefix.');
            }
            $value .= (string) ($low + $choose($count));
        }
        return str_repeat('0', $choose($this->paddingChoices)) . $value;
    }
}
