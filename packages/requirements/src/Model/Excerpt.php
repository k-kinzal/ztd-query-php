<?php

declare(strict_types=1);

namespace Requirements\Model;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

/**
 * One piece of evidence: a quotation and the selector of the source unit it quotes.
 *
 * The quotation must equal the complete text of the selected unit.
 */
final class Excerpt
{
    /**
     * @param string $selector Identifies exactly one unit of the item's source
     * @param string $quote The complete text of that unit
     */
    public function __construct(public readonly string $selector, public readonly string $quote)
    {
    }

    /**
     * Reads an evidence entry of a definition.
     *
     * @param mixed $value The decoded evidence entry
     *
     * @return self The excerpt
     *
     * @throws InvalidInputException When the entry has unknown fields or lacks a selector or quote
     */
    public static function from(mixed $value): self
    {
        $data = Fields::mapping($value, 'evidence');
        Fields::keys($data, ['selector', 'quote'], 'evidence');
        return new self(Fields::text($data, 'selector'), Fields::text($data, 'quote'));
    }
}
