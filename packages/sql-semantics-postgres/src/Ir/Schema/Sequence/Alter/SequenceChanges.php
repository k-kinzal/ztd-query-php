<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwner;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceIntegerOrder;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Assignments that change a native generator or its owning dependency.
 *
 * An omitted value retains the existing setting according to the native
 * type-change rules. The default token explicitly resets a bound or restarts
 * at the effective recorded start of the same operation.
 *
 * @visibility public
 * @example Changing the recorded start and immediately using it
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceChanges(start: new \SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral('12'), restart: \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceResetOperand::Native))->toString() // => ' START WITH 12 RESTART'
 */
final class SequenceChanges
{
    use Snapshot;

    /**
     * Checks every constraint determined by the assignments themselves.
     */
    public function __construct(
        public readonly ?IntegerStorage $storage = null,
        public readonly ?WideIntegerLiteral $increment = null,
        public readonly WideIntegerLiteral|SequenceResetOperand|null $minimum = null,
        public readonly WideIntegerLiteral|SequenceResetOperand|null $maximum = null,
        public readonly ?WideIntegerLiteral $start = null,
        public readonly WideIntegerLiteral|SequenceResetOperand|null $restart = null,
        public readonly ?WideIntegerLiteral $cache = null,
        public readonly ?bool $cycle = null,
        public readonly SequenceOwner|ClearSequenceOwner|null $owner = null,
    ) {
        Check::input(
            $storage !== null || $increment !== null || $minimum !== null || $maximum !== null || $start !== null
                || $restart !== null || $cache !== null || $cycle !== null || $owner !== null,
            'A sequence change requires at least one assignment.'
        );
        Check::input($increment?->decimal !== '0', 'A sequence increment cannot be zero.');
        Check::input($cache === null || SequenceIntegerOrder::compare($cache, new WideIntegerLiteral('0')) > 0, 'A sequence cache must be positive.');
        SequenceChangeRules::checkLimits($storage, $increment, $minimum, $maximum, $start, $restart);
    }

    /**
     * Emits the clauses in native interpretation order.
     */
    public function toString(): string
    {
        return ($this->storage === null ? '' : ' AS ' . $this->storage->value)
            . ($this->increment === null ? '' : ' INCREMENT BY ' . $this->increment->decimal)
            . SequenceChangeRules::bound('MINVALUE', $this->minimum)
            . SequenceChangeRules::bound('MAXVALUE', $this->maximum)
            . ($this->start === null ? '' : ' START WITH ' . $this->start->decimal)
            . match (true) {
                $this->restart instanceof WideIntegerLiteral => ' RESTART WITH ' . $this->restart->decimal,
                $this->restart === SequenceResetOperand::Native => ' RESTART',
                $this->restart === null => '',
            }
        . ($this->cache === null ? '' : ' CACHE ' . $this->cache->decimal)
        . match ($this->cycle) {
            null => '',
            true => ' CYCLE',
            false => ' NO CYCLE',
        }
        . match (true) {
            $this->owner instanceof SequenceOwner => ' OWNED BY ' . $this->owner->quoted(),
            $this->owner === ClearSequenceOwner::Remove => ' OWNED BY NONE',
            $this->owner === null => '',
        };
    }
}
