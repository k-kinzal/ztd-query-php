<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Effective native sequence settings and initial state.
 *
 * @visibility public
 * @example Resolving the ascending defaults of an integer sequence
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceParameters(\SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage::Standard))->maximum->decimal // => '2147483647'
 */
final class SequenceParameters
{
    use Snapshot;

    /**
     * The inclusive lower limit.
     */
    public readonly WideIntegerLiteral $minimum;

    /**
     * The inclusive upper limit.
     */
    public readonly WideIntegerLiteral $maximum;

    /**
     * The catalog start used by RESTART without a value.
     */
    public readonly WideIntegerLiteral $start;

    /**
     * The value returned by the first nextval call.
     */
    public readonly WideIntegerLiteral $initial;

    /**
     * Resolves native defaults and validates the finished generator domain.
     */
    public function __construct(
        public readonly IntegerStorage $storage = IntegerStorage::Wide,
        public readonly WideIntegerLiteral $increment = new WideIntegerLiteral('1'),
        ?WideIntegerLiteral $minimum = null,
        ?WideIntegerLiteral $maximum = null,
        ?WideIntegerLiteral $start = null,
        ?WideIntegerLiteral $initial = null,
        public readonly WideIntegerLiteral $cache = new WideIntegerLiteral('1'),
        public readonly bool $cycle = false,
    ) {
        Check::input($increment->decimal !== '0', 'A sequence increment cannot be zero.');
        Check::input($cache->decimal !== '0' && !str_starts_with($cache->decimal, '-'), 'A sequence cache must be positive.');
        [$first, $last] = match ($storage) {
            IntegerStorage::Small => ['-32768', '32767'],
            IntegerStorage::Standard => ['-2147483648', '2147483647'],
            IntegerStorage::Wide => ['-9223372036854775808', '9223372036854775807'],
        };
        $descending = str_starts_with($increment->decimal, '-');
        $lower = $minimum ?? new WideIntegerLiteral($descending ? $first : '1');
        $upper = $maximum ?? new WideIntegerLiteral($descending ? '-1' : $last);
        Check::input(
            SequenceIntegerOrder::compare(new WideIntegerLiteral($first), $lower) <= 0
            && SequenceIntegerOrder::compare($upper, new WideIntegerLiteral($last)) <= 0
            && SequenceIntegerOrder::compare($lower, $upper) < 0,
            'Sequence limits must increase within the selected integer storage.'
        );
        $catalogStart = $start ?? ($descending ? $upper : $lower);
        $restart = $initial ?? $catalogStart;
        Check::input(
            SequenceIntegerOrder::compare($lower, $catalogStart) <= 0 && SequenceIntegerOrder::compare($catalogStart, $upper) <= 0
            && SequenceIntegerOrder::compare($lower, $restart) <= 0 && SequenceIntegerOrder::compare($restart, $upper) <= 0,
            'The sequence start and restart must be within its limits.'
        );
        $this->minimum = $lower;
        $this->maximum = $upper;
        $this->start = $catalogStart;
        $this->initial = $restart;
    }
}
