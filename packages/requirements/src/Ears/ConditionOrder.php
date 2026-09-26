<?php

declare(strict_types=1);

namespace Requirements\Ears;

use Requirements\Input\InvalidInputException;

/**
 * Accepts the condition clauses of one statement in EARS order.
 *
 * The order is an optional feature (Where) at most once, then preconditions (While), then at
 * most one trigger (When or If). The trigger decides how the system clause must begin.
 */
final class ConditionOrder
{
    private int $rank = 0;

    private ?string $trigger = null;

    /**
     * Accepts the next condition clause.
     *
     * @param string $clause The clause text before the system response
     *
     * @throws InvalidInputException When the clause lacks a keyword or text, is out of order, or contains shall or then
     */
    public function accept(string $clause): void
    {
        if (preg_match('/^(where|while|when|if)\s+(.+)$/isuD', trim($clause), $match) !== 1 || !Wording::hasContent($match[2])) {
            throw new InvalidInputException('EARS: each condition needs Where, While, When or If and nonempty text.');
        }
        $keyword = strtolower($match[1]);
        $next = match ($keyword) {
            'where' => 1, 'while' => 2, default => 3
        };
        if ($next < $this->rank || ($next === 1 && $this->rank === 1) || ($next === 3 && $this->trigger !== null)) {
            throw new InvalidInputException('EARS: use optional feature, preconditions, then at most one trigger (When or If), in that order.');
        }
        if (preg_match('/\b(?:shall|then)\b/iu', $match[2]) === 1) {
            throw new InvalidInputException('EARS: shall belongs in the response; then must introduce the system clause after If.');
        }
        $this->rank = $next;
        if ($next === 3) {
            $this->trigger = $keyword;
        }
    }

    /**
     * Returns the trigger keyword accepted so far.
     *
     * @return string|null "when" or "if", or null without a trigger
     */
    public function trigger(): ?string
    {
        return $this->trigger;
    }
}
