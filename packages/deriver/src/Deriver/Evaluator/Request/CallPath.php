<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Request;

use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Exception\InvalidInputException;

/**
 * An ordered caller pattern ending immediately before the selected observation.
 * @visibility root
 */
final class CallPath
{
    /**
     * @param list<string> $callers Named callers with optional intermediate ellipses
     * @param bool $atCall Whether the final observation is a call site inside its owner
     * @throws InvalidInputException If the path is empty or contains malformed steps
     */
    public function __construct(public readonly array $callers, public readonly bool $atCall = false)
    {
        if ($callers === [] || $callers[0] === '...') {
            throw new InvalidInputException('A call path must begin with a named caller.');
        }
        $identifier = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*';
        foreach ($callers as $index => $caller) {
            if ($caller === '...' && ($callers[$index - 1] ?? '') !== '...') {
                continue;
            }
            if (preg_match('~\A\\\\?(?:' . $identifier . '\\\\)*' . $identifier . '(?:::' . $identifier . ')?\z~', $caller) !== 1) {
                throw new InvalidInputException('Call paths require callable names separated by ->; use ... only between named targets.');
            }
        }
    }

    /**
     * Matches backwards, with an ellipsis consuming zero or more intermediate callers.
     * @return list<int> Remaining pattern positions after this caller
     */
    public function advance(string $caller, int $position): array
    {
        $identity = new CallableIdentity();
        $step = $this->callers[$position];
        if ($step !== '...') {
            return $identity->key($caller) === $identity->key($step) ? [$position - 1] : [];
        }
        return $identity->key($caller) === $identity->key($this->callers[$position - 1]) ? [$position - 2, $position] : [$position];
    }
}
