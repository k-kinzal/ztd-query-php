<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Kind;

/**
 * A user variable read: its value now, converted to the domain it had when the statement was resolved.
 *
 * @visibility MySqlMemory
 */
final class UserVariableRead implements Evaluable
{
    /**
     * @param string $name The variable name
     * @param Domain $domain The domain the variable had when the statement was resolved
     */
    public function __construct(public readonly string $name, public readonly Domain $domain)
    {
    }

    /**
     * Answers the domain of the variable.
     */
    #[\Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Reads the variable.
     */
    #[\Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        [$value, $domain] = $frame->context->variables->user($this->name);
        if ($value === null || $domain->kind === $this->domain->kind) {
            return $value;
        }

        return match ($this->domain->kind) {
            Kind::Integer => Convert::toInteger($value, $domain, $frame->context),
            Kind::Decimal => Convert::toDecimal($value, $domain, $frame->context),
            Kind::Double => Convert::toDouble($value, $domain, $frame->context),
            default => Convert::toText($value, $domain),
        };
    }
}
