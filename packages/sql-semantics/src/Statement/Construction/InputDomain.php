<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction;

use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Validation\Check;

/**
 * The closed set of immutable new scalar inputs; interface implementation is insufficient.
 * @visibility SqlSemantics
 */
final class InputDomain
{
    /**
     * Every concrete member validates its children when constructed.
     */
    public const TYPES = [
        E\NullConstant::class, E\SqliteInteger::class, E\SqliteReal::class,
        E\SqliteText::class, E\SqliteBlob::class, E\SqliteCurrentTime::class,
        Expression\ColumnUse::class, Expression\GroupedInput::class,
        Expression\UnaryInput::class, Expression\BinaryInput::class,
        Expression\BetweenInput::class, Expression\InListInput::class,
        Expression\CastInput::class, Expression\CollationInput::class,
        Conditional\SearchedCaseInput::class, Conditional\SimpleCaseInput::class,
        Subquery\ScalarQueryInput::class, Subquery\ExistsInput::class, Subquery\InQueryInput::class,
    ];

    /**
     * Rejects external implementations before they can become reachable from an input value.
     */
    public static function check(ScalarInput $input): void
    {
        Check::input(in_array($input::class, self::TYPES, true), 'A new scalar input must be a registered immutable concrete type.');
    }
}
