<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Value;

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

    private readonly string $sampleMaximum;

    /**
     * Binds normalized inclusive magnitudes; leading zeroes are spelling choices, not magnitude changes.
     * @throws InvalidArgumentException When magnitudes or padding bounds are invalid
     */
    public function __construct(private readonly string $minimum, private readonly ?string $maximum, private readonly int $maximumLeadingZeroes = 16, private readonly bool $digitSeparators = false)
    {
        $sampleMaximum = $maximum ?? str_repeat('9', max(65, strlen($minimum)));
        $widthChoices = strlen($sampleMaximum) - strlen($minimum) + 1;
        $paddingChoices = $maximumLeadingZeroes + 1;
        if ($paddingChoices < 1 || $paddingChoices > 1025 || $widthChoices < 1 || !$this->normalized($minimum)
            || !$this->normalized($sampleMaximum)
            || strlen($minimum) > strlen($sampleMaximum)
            || (strlen($minimum) === strlen($sampleMaximum) && strcmp($minimum, $sampleMaximum) > 0)) {
            throw new InvalidArgumentException('Require normalized unsigned decimal bounds in ascending order.');
        }
        $this->sampleMaximum = $sampleMaximum;
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
        $upper = $length === strlen($this->sampleMaximum) ? $this->sampleMaximum : str_repeat('9', $length);
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
    /**
     * Checks declaration bounds without interpreting them as machine integers.
     */
    public function normalized(string $value): bool
    {
        return $value !== '' && strspn($value, '0123456789') === strlen($value)
            && ($value === '0' || $value[0] !== '0');
    }

    /**
     * @return list<int>
     */
    #[Override]
    public function match(string $value, int $offset = 0): array
    {
        $ends = [];
        $digits = '';
        while (isset($value[$offset])) {
            $character = $value[$offset++];
            if ($this->digitSeparators && $character === '_' && $digits !== ''
                && isset($value[$offset]) && str_contains('0123456789', $value[$offset])) {
                $character = $value[$offset++];
            }
            if (!str_contains('0123456789', $character)) {
                break;
            }
            $digits .= $character;
            $magnitude = ltrim($digits, '0');
            $magnitude = $magnitude === '' ? '0' : $magnitude;
            if ($this->compare($magnitude, $this->minimum) >= 0 && ($this->maximum === null || $this->compare($magnitude, $this->maximum) <= 0)) {
                $ends[] = $offset;
            }
        }
        return $ends;
    }

    /**
     * Orders normalized magnitudes by width and then by digits.
     */
    public function compare(string $left, string $right): int
    {
        $width = strlen($left) <=> strlen($right);
        return $width === 0 ? strcmp($left, $right) : $width;
    }

}
