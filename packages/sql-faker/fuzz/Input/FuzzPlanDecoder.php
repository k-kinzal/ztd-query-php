<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Input;

use InvalidArgumentException;
use SqlFaker\Grammar\Derivation\GenerationPlan;

/**
 * Decodes every byte string into a bounded non-empty generation plan.
 */
final class FuzzPlanDecoder
{
    /**
     * Version of the raw binary input layout.
     */
    public const FORMAT = 1;

    /**
     * @throws InvalidArgumentException When the run cannot finish its minimum statement
     */
    public function __construct(public readonly int $minimum, public readonly int $maximum = 5000)
    {
        if ($minimum < 1 || $maximum < $minimum || $maximum > 1000000) {
            throw new InvalidArgumentException('Invalid expansion budget: require 1 <= minimum <= maximum <= 1000000.');
        }
    }

    /**
     * Pads a short header and separates structure from lexical bytes.
     *
     * @return GenerationPlan<true>
     */
    public function decode(string $input): GenerationPlan
    {
        $header = 0;
        $range = $this->maximum - $this->minimum + 1;
        for ($index = 3; $index >= 0; --$index) {
            $header = ($header * 256 + (isset($input[$index]) ? ord($input[$index]) : 0)) % $range;
        }
        $structure = '';
        $lexical = '';
        for ($index = 4; $index < strlen($input); $index += 2) {
            $structure .= $input[$index];
            $lexical .= $input[$index + 1] ?? '';
        }
        $budget = $this->minimum + $header;
        return GenerationPlan::all()->requiringNonEmpty()->withExpansionBudget($budget)->withChoiceBytes($structure, $lexical);
    }

    /**
     * Encodes a witness using the same byte layout as subsequent mutations.
     *
     * @throws InvalidArgumentException When the requested budget lies outside this run's range
     */
    public function encode(int $budget, string $structure, string $lexical = ''): string
    {
        if ($budget < $this->minimum || $budget > $this->maximum) {
            throw new InvalidArgumentException('Witness budget lies outside the decoder range.');
        }
        $input = pack('V', $budget - $this->minimum);
        for ($index = 0; $index < max(strlen($structure), strlen($lexical)); ++$index) {
            $input .= ($structure[$index] ?? "\0") . ($lexical[$index] ?? "\0");
        }
        return $input;
    }
}
