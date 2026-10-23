<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Reads a buffered result column, including its initialized state before any row is stored.
 *
 * Empty set operations retain NULL in nullable fields and a zero value in other fields.
 * Reading or discarding rows replaces that state (verified through SQL on MySQL 8.4.7).
 * This state belongs to the result buffer, not to the input expressions of its operands.
 *
 * @visibility MySqlMemory
 */
final class BufferedRead implements Evaluable
{
    /**
     * @param Domain $domain The stored column domain
     * @param int $position The column position
     * @param bool|null $nullableInitial The initial NULL marker, or the domain default
     * @param bool $initialOnly Whether an empty result always exposes its initial field state
     */
    public function __construct(public readonly Domain $domain, public readonly int $position, public readonly ?bool $nullableInitial = null, public readonly bool $initialOnly = false)
    {
    }

    /**
     * Answers the buffered column's domain.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Reads the last buffered row, retaining NULL as a value rather than an absent slot.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        return !$this->initialOnly && array_key_exists($this->position, $frame->buffered) ? $frame->buffered[$this->position] : $this->initial();
    }

    /**
     * Answers the field's initial value without evaluating an operand or generating diagnostics.
     */
    public function initial(): int|float|string|null
    {
        if ($this->nullableInitial ?? $this->domain->nullable) {
            return null;
        }

        return match ($this->domain->kind) {
            Kind::Null => null,
            Kind::Integer, Kind::Year => 0,
            Kind::Double => 0.0,
            Kind::Decimal => $this->domain->decimals > 0 ? '0.' . str_repeat('0', $this->domain->decimals) : '0',
            Kind::Date => '0000-00-00',
            Kind::DateTime => '0000-00-00 00:00:00' . ($this->domain->decimals > 0 ? '.' . str_repeat('0', $this->domain->decimals) : ''),
            Kind::Time => '00:00:00' . ($this->domain->decimals > 0 ? '.' . str_repeat('0', $this->domain->decimals) : ''),
            Kind::Json => 'null',
            Kind::Bit => str_repeat("\0", (int) ceil($this->domain->length / 8)),
            Kind::String => '',
        };
    }
}
