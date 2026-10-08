<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * A TIMESTAMP value as a DATETIME in UTC: CAST(value AT TIME ZONE 'UTC' AS DATETIME).
 *
 * The emulator keeps every TIMESTAMP in UTC. The server converts the value through whole
 * seconds, so the fraction of a TIMESTAMP(N) is dropped and the DATETIME(N) result writes zeros
 * in its place (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast.
 *
 * @visibility MySqlMemory
 */
final class Zoned implements Evaluable
{
    /**
     * @param Evaluable $operand The TIMESTAMP value, or NULL
     * @param Domain $domain The domain of the DATETIME result
     */
    public function __construct(public readonly Evaluable $operand, public readonly Domain $domain)
    {
    }

    /**
     * Answers the domain of the result.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Converts the value for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): ?string
    {
        $value = $this->operand->evaluate($frame);
        if ($value === null) {
            return null;
        }

        return substr((string) $value, 0, 19) . ($this->domain->decimals > 0 ? '.' . str_repeat('0', $this->domain->decimals) : '');
    }
}
