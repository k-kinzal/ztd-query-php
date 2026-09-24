<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Condition;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A five-character SQLSTATE naming a signaled condition; the completion class 00 cannot be signaled.
 * @visibility public
 * @example Reading the condition class
 *     (new \SqlSemantics\Model\Configuration\Condition\SqlState('45000'))->conditionClass() // => '45'
 */
final class SqlState
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $code)
    {
        if (preg_match('/^[0-9A-Z]{5}$/D', $code) !== 1 || str_starts_with($code, '00')) {
            throw new InvalidStructure('An SQLSTATE has five digits or uppercase letters and is not a completion condition.');
        }
    }

    /**
     * Returns the two-character class: 01 is a warning, 02 not found, and any other class an exception.
     */
    public function conditionClass(): string
    {
        return substr($this->code, 0, 2);
    }
}
