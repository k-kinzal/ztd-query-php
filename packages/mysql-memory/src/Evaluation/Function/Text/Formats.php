<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Text;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Numbers;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale;

/**
 * FORMAT(X, D[, locale]): a number rounded to D decimals and written with the separators of a locale.
 *
 * D counts as a 32-bit integer: a negative count is 0 and a count above 30 is 30. A DOUBLE, a
 * string, a JSON value or a temporal value is read as a double and rounded half to even, a negative zero keeping
 * its sign; any other value is read as an exact decimal and rounded half away from zero. The
 * locale is one lc_time_names takes, named without regard to case; the default is en_US, and
 * another name, NULL included, warns (ER_UNKNOWN_LOCALE) and writes as en_US. A locale written
 * as a literal is read once, when the statement is resolved, so it warns once even when no row
 * is formatted. The separators of each locale were read from a live 8.4 server: bg_BG separates
 * thousands with a NUL byte, and en_IN, ta_IN and te_IN group the digits above the last three by
 * two.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_format,
 * https://dev.mysql.com/doc/refman/8.4/en/locale-support.html.
 *
 * @visibility MySqlMemory
 */
final class Formats
{
    /**
     * The decimal point, the thousands separator and the grouping of each locale that does not write as en_US does; an empty grouping writes no separator.
     *
     * @var array<string, array{string, string, list<int>}>
     */
    public const SEPARATORS = [
        'ar_SA' => ['.', '', []], 'sr_RS' => ['.', '', []],
        'be_BY' => [',', '.', [3]], 'da_DK' => [',', '.', [3]], 'de_BE' => [',', '.', [3]], 'de_DE' => [',', '.', [3]], 'de_LU' => [',', '.', [3]],
        'es_ES' => [',', '.', [3]], 'es_AR' => [',', '.', [3]], 'es_BO' => [',', '.', [3]], 'es_CL' => [',', '.', [3]], 'es_CO' => [',', '.', [3]],
        'es_EC' => [',', '.', [3]], 'es_PY' => [',', '.', [3]], 'es_UY' => [',', '.', [3]], 'es_VE' => [',', '.', [3]], 'fo_FO' => [',', '.', [3]],
        'hu_HU' => [',', '.', [3]], 'id_ID' => [',', '.', [3]], 'is_IS' => [',', '.', [3]], 'lt_LT' => [',', '.', [3]], 'mn_MN' => [',', '.', [3]],
        'nb_NO' => [',', '.', [3]], 'no_NO' => [',', '.', [3]], 'ro_RO' => [',', '.', [3]], 'ru_UA' => [',', '.', [3]], 'sq_AL' => [',', '.', [3]],
        'tr_TR' => [',', '.', [3]], 'uk_UA' => [',', '.', [3]], 'vi_VN' => [',', '.', [3]],
        'bg_BG' => [',', "\0", [3]],
        'ca_ES' => [',', '', []], 'de_AT' => [',', '', []], 'el_GR' => [',', '', []], 'eu_ES' => [',', '', []], 'fr_BE' => [',', '', []],
        'fr_CH' => [',', '', []], 'fr_LU' => [',', '', []], 'fr_CA' => [',', '', []], 'fr_FR' => [',', '', []], 'gl_ES' => [',', '', []],
        'hr_HR' => [',', '', []], 'it_IT' => [',', '', []], 'nl_BE' => [',', '', []], 'nl_NL' => [',', '', []], 'pl_PL' => [',', '', []],
        'pt_BR' => [',', '', []], 'pt_PT' => [',', '', []], 'sl_SI' => [',', '', []],
        'cs_CZ' => [',', ' ', [3]], 'es_CR' => [',', ' ', [3]], 'et_EE' => [',', ' ', [3]], 'fi_FI' => [',', ' ', [3]], 'lv_LV' => [',', ' ', [3]],
        'mk_MK' => [',', ' ', [3]], 'ru_RU' => [',', ' ', [3]], 'sk_SK' => [',', ' ', [3]], 'sv_FI' => [',', ' ', [3]], 'sv_SE' => [',', ' ', [3]],
        'de_CH' => ['.', "'", [3]],
        'en_IN' => ['.', ',', [3, 2]], 'ta_IN' => ['.', ',', [3, 2]], 'te_IN' => ['.', ',', [3, 2]],
        'it_CH' => [',', "'", [3]], 'rm_CH' => [',', "'", [3]],
    ];

    /**
     * The most decimals FORMAT writes.
     */
    public const MAX_DECIMALS = 30;

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [new Routine('FORMAT', 2, 3, $this->format(...), 1, $this->resolve(...))];
    }

    /**
     * Reads a locale known when the statement is resolved once, warning then, and gives the body its name.
     *
     * @param list<Evaluable> $arguments
     * @param list<bool> $known Whether each argument is known when the statement is resolved
     * @return list<Evaluable>
     *
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors
     */
    public function resolve(Frame $frame, array $arguments, array $known): array
    {
        $locale = $arguments[2] ?? null;
        while ($locale instanceof \MySqlMemory\Evaluation\Leaf\Retyped) {
            $locale = $locale->evaluable;
        }
        if (isset($arguments[2]) && $locale instanceof Constant && ($known[2] ?? false)) {
            $arguments[2] = new Constant(Domain::string(5, Collation::known('ascii_general_ci')), $this->locale($frame, $arguments[2]));
        }

        return $arguments;
    }

    /**
     * Answers the name of the locale an argument names, or en_US after a warning when it names none.
     *
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors
     */
    public function locale(Frame $frame, Evaluable $argument): string
    {
        $domain = $argument->domain();
        $text = Convert::toText($argument->evaluate($frame), $domain);
        $text = $text === null ? null : Encoding::convert($text, $domain->kind === Kind::String ? $domain->collation->charset : Charset::known('utf8mb4'), Charset::known('utf8mb4'));
        $locale = $text === null ? null : Locale::named($text);
        if ($locale === null) {
            $frame->context->warning(AdministrationError::UnknownLocale, $text === null ? 'NULL' : mb_substr($text, 0, 64, 'UTF-8'));

            return 'en_US';
        }

        return $locale->name;
    }

    /**
     * FORMAT: the number rounded and written with the separators of the locale, NULL when the number or the count is NULL.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors
     */
    public function format(Frame $frame, array $arguments, Domain $result): ?string
    {
        $count = Convert::toInteger($arguments[1]->evaluate($frame), $arguments[1]->domain(), $frame->context);
        if ($count === null) {
            return null;
        }
        $locale = isset($arguments[2]) ? $this->locale($frame, $arguments[2]) : 'en_US';
        $value = $arguments[0]->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $places = $this->places($count);
        $domain = $arguments[0]->domain();
        if (in_array($domain->kind, [Kind::Double, Kind::String, Kind::Json, Kind::Date, Kind::Time, Kind::DateTime], true) && !$domain->numericBytes) {
            $real = Convert::toDouble($value, $domain, $frame->context);
            if ($real === null) {
                return null;
            }
            $rounded = (new Numbers())->roundReal($real, $places, false);
            $negative = $rounded < 0 || ($rounded === 0.0 && fdiv(1, $rounded) < 0);
            $number = Decimal::round(Decimal::fromDouble(abs($rounded)), $places);
        } else {
            $decimal = Decimal::round((string) Convert::toDecimal($value, $domain, $frame->context), $places);
            $negative = str_starts_with($decimal, '-');
            $number = ltrim($decimal, '-');
        }
        $text = ($negative ? '-' : '') . $this->written($number, $locale);

        return $result->kind === Kind::String ? Encoding::convert($text, Charset::known('utf8mb4'), $result->collation->charset) : $text;
    }

    /**
     * Answers the decimals a count asks for: the count as a 32-bit integer, from 0 to 30.
     */
    public function places(int $count): int
    {
        $low = $count & 0xFFFFFFFF;
        $signed = $low >= 0x80000000 ? $low - 0x100000000 : $low;

        return max(0, min(self::MAX_DECIMALS, $signed));
    }

    /**
     * Writes an unsigned decimal with the decimal point, thousands separator and grouping of a locale.
     */
    public function written(string $number, string $locale): string
    {
        [$point, $separator, $grouping] = self::SEPARATORS[$locale] ?? ['.', ',', [3]];
        $parts = explode('.', $number, 2);
        $digits = $parts[0];
        $groups = [];
        for ($index = 0; $grouping !== [] && strlen($digits) > ($size = $grouping[min($index, count($grouping) - 1)]); $index++) {
            array_unshift($groups, substr($digits, -$size));
            $digits = substr($digits, 0, -$size);
        }
        array_unshift($groups, $digits);
        $integer = implode($separator, $groups);

        return isset($parts[1]) ? $integer . $point . $parts[1] : $integer;
    }
}
