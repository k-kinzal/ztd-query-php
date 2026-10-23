<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\Moments;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Resolves the results of date functions: DATE is a date, YEAR a year, each other part of a date or time a BIGINT as wide as its digits and a sign.
 *
 * A function that gives a TIME or DATETIME keeps the fractional digits of its arguments
 * (Seconds); the formatting functions are typed by their format (Formats); DAYNAME and MONTHNAME
 * are as long as the longest name of the lc_time_names locale. MySQL 5.6 and 5.7 give several
 * results other lengths, and YEAR is a BIGINT before 8.4 (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class DateResults
{
    private const PARTS = [
        'MONTH' => 3, 'DAY' => 3, 'DAYOFMONTH' => 3, 'HOUR' => 4, 'MINUTE' => 3, 'SECOND' => 3,
        'MICROSECOND' => 21, 'QUARTER' => 2, 'DAYOFYEAR' => 4, 'DAYOFWEEK' => 2, 'WEEKDAY' => 2,
    ];

    private const LEGACY_PARTS = [
        'MONTH' => 2, 'DAY' => 2, 'DAYOFMONTH' => 2, 'HOUR' => 2, 'MINUTE' => 2, 'SECOND' => 2,
        'MICROSECOND' => 21, 'QUARTER' => 1, 'DAYOFYEAR' => 3, 'DAYOFWEEK' => 1, 'WEEKDAY' => 1,
    ];

    /**
     * The length of each function that gives a BIGINT, in 8.0 and later and in 5.6 and 5.7.
     */
    private const COUNTS = [
        'DATEDIFF' => [9, 7], 'TIMESTAMPDIFF' => [21, 21], 'TIME_TO_SEC' => [10, 10], 'TO_DAYS' => [8, 6], 'TO_SECONDS' => [21, 6],
        'WEEK' => [3, 2], 'WEEKOFYEAR' => [3, 2], 'YEARWEEK' => [7, 6], 'PERIOD_ADD' => [21, 6], 'PERIOD_DIFF' => [21, 6],
    ];

    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        $seconds = new Seconds();
        $formats = new Formats();
        $rules = [
            'DATE' => static fn (Invocation $call): Domain => new Domain(Kind::Date, Field::Date, 10),
            'YEAR' => fn (Invocation $call): Domain => match (true) {
                $this->legacy($call) => Domain::integer(Field::LongLong, 4),
                $call->derivation->context->profile->grammar === GrammarRelease::MySql8044 => Domain::integer(Field::LongLong, 5),
                default => new Domain(Kind::Year, Field::Year, 4, 0, true),
            },
            'MAKEDATE' => static fn (Invocation $call): Domain => new Domain(Kind::Date, Field::Date, 10),
            'FROM_DAYS' => static fn (Invocation $call): Domain => new Domain(Kind::Date, Field::Date, 10),
            'LAST_DAY' => static fn (Invocation $call): Domain => new Domain(Kind::Date, Field::Date, 10),
            'TIME' => fn (Invocation $call): Domain => $this->time($seconds->digits($call, 0, true)),
            'MAKETIME' => fn (Invocation $call): Domain => $this->time($seconds->digits($call, 2, false)),
            'SEC_TO_TIME' => fn (Invocation $call): Domain => $this->time($seconds->digits($call, 0, false)),
            'TIMEDIFF' => fn (Invocation $call): Domain => $this->time(max($seconds->digits($call, 0, true), $seconds->digits($call, 1, true))),
            'TIMESTAMP' => fn (Invocation $call): Domain => $this->dateTime(max($seconds->digits($call, 0, false), count($call->domains) > 1 ? $seconds->digits($call, 1, true) : 0)),
            'CONVERT_TZ' => fn (Invocation $call): Domain => $this->dateTime($seconds->digits($call, 0, false)),
            'ADDTIME' => fn (Invocation $call): Domain => $this->added($call, $seconds),
            'SUBTIME' => fn (Invocation $call): Domain => $this->added($call, $seconds),
            'UNIX_TIMESTAMP' => fn (Invocation $call): Domain => $this->unix($call, $call->domains === [] ? 0 : $seconds->digits($call, 0, false)),
            'FROM_UNIXTIME' => fn (Invocation $call): Domain => count($call->domains) > 1 ? $this->written($call, $formats->written($call, 1)) : $this->dateTime($seconds->digits($call, 0, false)),
            'DATE_FORMAT' => fn (Invocation $call): Domain => $this->written($call, $formats->written($call, 1)),
            'TIME_FORMAT' => fn (Invocation $call): Domain => $this->written($call, $formats->written($call, 1)),
            'STR_TO_DATE' => static fn (Invocation $call): Domain => $formats->parsed($call, 1),
            'ADDDATE' => fn (Invocation $call): Domain => (new Moments($call->settings))->shifted($call->domain(0), IntervalUnit::Day, 0, $this->legacy($call)),
            'SUBDATE' => fn (Invocation $call): Domain => (new Moments($call->settings))->shifted($call->domain(0), IntervalUnit::Day, 0, $this->legacy($call)),
            'GET_FORMAT' => fn (Invocation $call): Domain => $this->written($call, Domain::string(17, $call->settings->connection, Field::VarString, Coercibility::Coercible)),
            'DAYNAME' => fn (Invocation $call): Domain => $this->name($call, $call->settings->locale()->longestDay()),
            'MONTHNAME' => fn (Invocation $call): Domain => $this->name($call, $call->settings->locale()->longestMonth()),
        ];
        foreach (self::PARTS as $name => $length) {
            $rules[$name] = fn (Invocation $call): Domain => Domain::integer(Field::LongLong, $this->legacy($call) ? self::LEGACY_PARTS[$name] : $length);
        }
        foreach (self::COUNTS as $name => [$length, $legacy]) {
            $rules[$name] = fn (Invocation $call): Domain => Domain::integer(Field::LongLong, $this->legacy($call) ? $legacy : $length);
        }

        return $rules;
    }

    /**
     * Tells whether the call is resolved for MySQL 5.6 or 5.7.
     */
    public function legacy(Invocation $call): bool
    {
        $grammar = $call->derivation->context->profile->grammar;

        return $grammar === GrammarRelease::MySql5651 || $grammar === GrammarRelease::MySql5744;
    }

    /**
     * Resolves a TIME with fractional digits.
     */
    public function time(int $decimals): Domain
    {
        return new Domain(Kind::Time, Field::Time, 10 + ($decimals > 0 ? $decimals + 1 : 0), $decimals);
    }

    /**
     * Resolves a DATETIME with fractional digits.
     */
    public function dateTime(int $decimals): Domain
    {
        return new Domain(Kind::DateTime, Field::DateTime, 19 + ($decimals > 0 ? $decimals + 1 : 0), $decimals);
    }

    /**
     * Resolves ADDTIME and SUBTIME: a DATETIME for a date or datetime, a TIME for a time, else a string of 29 characters.
     *
     * MySQL 5.6 and 5.7 give a string for a DATE as well (verified on live 5.6.51 and 5.7.44 servers).
     */
    public function added(Invocation $call, Seconds $seconds): Domain
    {
        $decimals = max($seconds->digits($call, 0, false), $seconds->digits($call, 1, true));

        return match ($call->domain(0)->kind) {
            Kind::DateTime => $this->dateTime($decimals),
            Kind::Date => $this->legacy($call) ? Domain::string(29, $call->settings->connection, Field::String, Coercibility::Coercible) : $this->dateTime($decimals),
            Kind::Time => $this->time($decimals),
            Kind::Null, Kind::Integer, Kind::Decimal, Kind::Double, Kind::String, Kind::Year, Kind::Json, Kind::Bit => Domain::string(29, $call->settings->connection, Field::String, Coercibility::Coercible),
        };
    }

    /**
     * Resolves UNIX_TIMESTAMP: a BIGINT, or a DECIMAL with the fractional digits of its argument.
     */
    public function unix(Invocation $call, int $decimals): Domain
    {
        $legacy = $this->legacy($call);
        if ($decimals === 0) {
            return Domain::integer(Field::LongLong, $legacy ? 11 : 21);
        }

        return Domain::decimal(($legacy ? 10 : 11) + $decimals, $decimals);
    }

    /**
     * Resolves DAYNAME and MONTHNAME: a string in the connection collation as long as the longest name of the locale.
     */
    public function name(Invocation $call, int $length): Domain
    {
        return $this->written($call, Domain::string($length, $call->settings->connection, Field::VarString, Coercibility::Coercible));
    }

    /**
     * Answers a string a date function writes as the release reports it: MySQL 5.6 and 5.7 report no decimals for it.
     */
    public function written(Invocation $call, Domain $domain): Domain
    {
        return $this->legacy($call) ? new Domain($domain->kind, $domain->field, $domain->length, 0, false, $domain->collation, [], $domain->coercibility) : $domain;
    }
}
