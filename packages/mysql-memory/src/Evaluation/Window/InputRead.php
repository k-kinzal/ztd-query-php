<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Window;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * Reads the field of the most recently consumed window input, including lookahead.
 *
 * MySQL 8.0 reads bare assignments through this field, independently of later
 * assignments to the same variable. Argument fields use negative slot positions;
 * ordinary copied outputs use their nonnegative output position. Both remain in
 * the execution frame so repeated subquery reads retain their own field state.
 * Verified through PDO with assignments in window arguments and sibling outputs.
 *
 * @visibility MySqlMemory
 */
final class InputRead implements Evaluable
{
    /**
     * @param Domain $domain The input field domain
     * @param int $position The retained field position
     */
    public function __construct(public readonly Domain $domain, public readonly int $position)
    {
    }

    /**
     * Answers the input field's domain.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Reads the retained field without accessing the current user-variable value.
     *
     * @phpstan-pure
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        return $frame->windowSlots[$this->position] ?? null;
    }
}
