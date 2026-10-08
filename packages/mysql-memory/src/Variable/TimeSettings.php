<?php

declare(strict_types=1);

namespace MySqlMemory\Variable;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Zone;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale;

/**
 * Checks the values of the variables of the clock of a session: time_zone, lc_time_names and timestamp.
 *
 * time_zone takes a string naming a zone, which it holds in the spelling of the time zone
 * tables, and refuses any other name with ER_UNKNOWN_TIME_ZONE. lc_time_names takes the name of a
 * locale, without regard to case, or its number, and refuses any other with ER_UNKNOWN_LOCALE.
 * timestamp takes a number of seconds from 1 to 2147483647, read as a double whose fraction is
 * rounded to microseconds, but never up to the next second; zero restores the clock, and so does a negative number, with a warning (verified on a live 8.4
 * server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_time_zone,
 * https://dev.mysql.com/doc/refman/8.4/en/locale-support.html,
 * https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_timestamp.
 *
 * @visibility MySqlMemory
 */
final class TimeSettings
{
    /**
     * The largest timestamp a session can set.
     */
    public const LATEST = 2147483647;

    /**
     * @param Context $context The statement
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Checks a value of time_zone and answers the name it holds.
     *
     * @throws \MySqlMemory\Error\SqlError When the value is not a string or names no zone
     */
    public function zone(int|float|string|null $value, Domain $domain): string
    {
        if ($value === null) {
            throw AdministrationError::WrongValueForVariable->error('time_zone', 'NULL');
        }
        if ($domain->kind !== Kind::String) {
            throw AdministrationError::WrongTypeForVariable->error('time_zone');
        }
        $zone = Zone::named((string) $value);
        if ($zone === null) {
            throw DataError::UnknownTimeZone->error((string) $value);
        }

        return $zone->name;
    }

    /**
     * Checks a value of lc_time_names and answers the name of the locale it holds.
     *
     * @throws \MySqlMemory\Error\SqlError When the value is NULL or names no locale
     */
    public function locale(int|float|string|null $value, Domain $domain): string
    {
        if ($value === null) {
            throw AdministrationError::WrongValueForVariable->error('lc_time_names', 'NULL');
        }
        if ($domain->kind === Kind::Integer) {
            $locale = Locale::numbered((int) $value);
        } elseif ($domain->kind === Kind::String) {
            $locale = Locale::named((string) $value);
        } else {
            throw AdministrationError::WrongTypeForVariable->error('lc_time_names');
        }

        if ($locale === null) {
            throw AdministrationError::UnknownLocale->error((string) Convert::toText($value, $domain));
        }

        return $locale->name;
    }

    /**
     * Checks a value of timestamp and answers the seconds it holds, or null when it restores the clock.
     *
     * @throws \MySqlMemory\Error\SqlError When the value is not a number, or is between 0 and 1 or beyond 2147483647
     */
    public function timestamp(int|float|string|null $value, Domain $domain): ?string
    {
        if ($value === null || !in_array($domain->kind, [Kind::Integer, Kind::Decimal, Kind::Double], true)) {
            throw AdministrationError::WrongTypeForVariable->error('timestamp');
        }
        $number = $domain->kind === Kind::Double ? Decimal::fromDouble((float) $value) : (string) Convert::toDecimal($value, $domain, $this->context);
        if (Decimal::compare($number, '0') < 0) {
            $this->context->warning(DataError::TruncatedWrongValue, 'timestamp', (string) Convert::toText($value, $domain));

            return null;
        }
        if (Decimal::compare($number, '0') === 0) {
            return null;
        }
        if (Decimal::compare($number, '1') < 0 || Decimal::compare($number, (string) self::LATEST) > 0) {
            throw AdministrationError::WrongValueForVariable->error('timestamp', $this->shown($value, $domain, $number));
        }

        $double = (float) $number;
        $seconds = floor($double);

        return sprintf('%d.%06d', $seconds, min(999999, (int) round(($double - $seconds) * 1000000)));
    }

    /**
     * Writes a refused timestamp as the server's message shows it: an integer or a double as the shortest double, a decimal as written.
     */
    public function shown(int|float|string $value, Domain $domain, string $number): string
    {
        if ($domain->kind === Kind::Decimal) {
            return (string) $value;
        }
        $text = var_export((float) $number, true);

        return str_replace(['E+', 'E', '.0e'], ['e', 'e', 'e'], str_ends_with($text, '.0') ? substr($text, 0, -2) : $text);
    }
}
