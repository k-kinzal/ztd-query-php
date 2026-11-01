<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

use Closure;
use IntlChar;
use Transliterator;

/**
 * The sets of characters a regular expression names: Unicode properties and the classes of the escapes \d, \s, \w, \h and \v.
 *
 * A property is named as ICU names it, loosely (case, spaces, hyphens and underscores do not
 * count): a general category, a script, a binary property, a block after "In", or `property=value`
 * for a general category, script, block, binary, enumerated, name, age or numeric value property;
 * and Any, ASCII and Assigned. A general category is left to PCRE; another set, a script among
 * them, is listed once for each process from the UnicodeSet ICU names by the same property and
 * value, through the intl extension, as Listing describes: ICU matches a script by the Script
 * property, where PCRE 10.40 and later match its Script_Extensions. The escapes have the
 * ICU definitions: \d is \p{Nd}, \s is [\t\n\f\r\p{Z}], \w is [\p{Alphabetic}\p{M}\p{Nd}\p{Pc}
 * U+200C U+200D], \h is [\t\p{Zs}] and \v the line terminators.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/regexp.html,
 * https://unicode-org.github.io/icu/userguide/strings/regexp.html,
 * https://unicode-org.github.io/icu/userguide/strings/unicodeset.html.
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Properties
{
    /**
     * The characters of \w beside letters, marks, decimal digits and connector punctuation: the joiners and the alphabetic symbols.
     */
    public const WORD = ['\p{L}', '\p{Nl}', '\p{M}', '\p{Nd}', '\p{Pc}', '\x{200C}', '\x{200D}', '\x{24B6}-\x{24E9}', '\x{1F130}-\x{1F149}', '\x{1F150}-\x{1F169}', '\x{1F170}-\x{1F189}'];

    /**
     * The line terminators.
     */
    public const LINE = ['\n', '\x{B}', '\f', '\r', '\x{85}', '\x{2028}', '\x{2029}'];

    /**
     * The ranges of each set listed from the character database, by key.
     *
     * @var array<string, string>
     */
    public static array $listed = [];

    /**
     * Answers the set an escape letter names: d, s, w, h or v, or their complements in upper case; matched without regard to case, closed over case before it is complemented.
     */
    public function escape(string $letter, bool $caseless = false): Members
    {
        $members = match (strtolower($letter)) {
            'd' => new Members(['\p{Nd}']),
            's' => new Members(['\t', '\n', '\f', '\r', '\p{Z}']),
            'w' => new Members(self::WORD),
            'h' => new Members(['\t', '\p{Zs}']),
            default => new Members(self::LINE),
        };

        $members = $caseless ? $members->closed() : $members;

        return ctype_upper($letter) ? $members->complement() : $members;
    }

    /**
     * Answers the set a property expression names, or null when it names none.
     */
    public function members(string $expression): ?Members
    {
        $parts = explode('=', $expression, 2);

        return count($parts) === 2 ? $this->valued(trim($parts[0]), trim($parts[1])) : $this->named(trim($expression));
    }

    /**
     * Answers the set of a property named alone: a general category, a script, a binary property, a block after "In", Any, ASCII or Assigned.
     */
    public function named(string $name): ?Members
    {
        $loose = strtolower((string) preg_replace('/[\s_\-]+/', '', $name));
        if ($loose === '') {
            return null;
        }
        $property = IntlChar::getPropertyEnum($name);
        if ($property >= IntlChar::PROPERTY_BINARY_START && $property < IntlChar::PROPERTY_BINARY_LIMIT) {
            return $this->listed('b' . $property, static fn (int $code): bool => IntlChar::hasBinaryProperty($code, $property), $this->pattern($property));
        }
        $category = $this->category($name);
        if ($category !== null) {
            return $category;
        }
        $script = $this->script($name);
        if ($script !== null) {
            return $script;
        }

        return match (true) {
            $loose === 'any' => new Members(['\x{0}-\x{10FFFF}']),
            $loose === 'word' => new Members(self::WORD),
            $loose === 'ascii' => new Members(['\x{0}-\x{7F}']),
            $loose === 'assigned' => new Members(['\p{Cn}'], true),
            str_starts_with($loose, 'in') => $this->valued('Block', substr(ltrim($name), 2)),
            default => null,
        };
    }

    /**
     * Tells whether a property expression names a block by the In shorthand: a name that is no binary property, general category or script, after "In".
     */
    public function shorthand(string $expression): bool
    {
        $name = trim($expression);
        $property = IntlChar::getPropertyEnum($name);

        return !str_contains($name, '=')
            && str_starts_with(strtolower((string) preg_replace('/[\s_\-]+/', '', $name)), 'in')
            && !($property >= IntlChar::PROPERTY_BINARY_START && $property < IntlChar::PROPERTY_BINARY_LIMIT)
            && IntlChar::getPropertyValueEnum(IntlChar::PROPERTY_GENERAL_CATEGORY_MASK, $name) === IntlChar::PROPERTY_INVALID_CODE
            && IntlChar::getPropertyValueEnum(IntlChar::PROPERTY_SCRIPT, $name) === IntlChar::PROPERTY_INVALID_CODE;
    }

    /**
     * Answers the set of `property=value`, or null when either is unknown.
     */
    public function valued(string $name, string $value): ?Members
    {
        $property = IntlChar::getPropertyEnum($name);
        if ($property === IntlChar::PROPERTY_GENERAL_CATEGORY || $property === IntlChar::PROPERTY_GENERAL_CATEGORY_MASK) {
            return $this->category($value);
        }
        if ($property === IntlChar::PROPERTY_SCRIPT) {
            return $this->script($value);
        }
        if ($property >= IntlChar::PROPERTY_BINARY_START && $property < IntlChar::PROPERTY_BINARY_LIMIT) {
            $truth = strtolower($value);
            $set = $this->listed('b' . $property, static fn (int $code): bool => IntlChar::hasBinaryProperty($code, $property), $this->pattern($property));

            return match (true) {
                in_array($truth, ['y', 'yes', 't', 'true'], true) => $set,
                in_array($truth, ['n', 'no', 'f', 'false'], true) => $set->complement(),
                default => null,
            };
        }
        if ($property >= IntlChar::PROPERTY_INT_START && $property < IntlChar::PROPERTY_INT_LIMIT) {
            $wanted = IntlChar::getPropertyValueEnum($property, $value);

            return $wanted === IntlChar::PROPERTY_INVALID_CODE ? null : $this->listed('i' . $property . '=' . $wanted, static fn (int $code): bool => IntlChar::getIntPropertyValue($code, $property) === $wanted, $this->pattern($property, $wanted));
        }

        return match ($property) {
            IntlChar::PROPERTY_NAME => $this->character($value),
            IntlChar::PROPERTY_AGE => $this->age($value),
            IntlChar::PROPERTY_NUMERIC_VALUE => is_numeric($value) ? $this->listed('n' . $value, static fn (int $code): bool => IntlChar::getNumericValue($code) === (float) $value, '[:Numeric_Value=' . (float) $value . ':]') : null,
            default => null,
        };
    }

    /**
     * Answers the set of a general category or a group of them, or null for no category.
     */
    public function category(string $name): ?Members
    {
        $mask = IntlChar::getPropertyValueEnum(IntlChar::PROPERTY_GENERAL_CATEGORY_MASK, $name);
        if ($mask === IntlChar::PROPERTY_INVALID_CODE) {
            return null;
        }
        $short = IntlChar::getPropertyValueName(IntlChar::PROPERTY_GENERAL_CATEGORY_MASK, $mask, IntlChar::SHORT_PROPERTY_NAME);

        return new Members(['\p{' . ($short === 'LC' ? 'L&' : $short) . '}']);
    }

    /**
     * Answers the set of a script, or null for no script.
     */
    public function script(string $name): ?Members
    {
        $script = IntlChar::getPropertyValueEnum(IntlChar::PROPERTY_SCRIPT, $name);

        return $script === IntlChar::PROPERTY_INVALID_CODE ? null : $this->listed('s' . $script, static fn (int $code): bool => IntlChar::getIntPropertyValue($code, IntlChar::PROPERTY_SCRIPT) === $script, $this->pattern(IntlChar::PROPERTY_SCRIPT, $script));
    }

    /**
     * Answers the set of the character of a Unicode name, or null for no such name.
     */
    public function character(string $name): ?Members
    {
        $character = $this->point($name);

        return $character === null ? null : Members::character($character);
    }

    /**
     * Answers the character of a Unicode name, the case of its letters aside, or null for no such name.
     */
    public function point(string $name): ?string
    {
        $names = Transliterator::create('Name-Any');
        $text = '\\N{' . $name . '}';
        $character = $names === null ? false : $names->transliterate($text);

        return $character === false || $character === $text || mb_strlen($character, 'UTF-8') !== 1 ? null : $character;
    }

    /**
     * Answers the characters assigned by a Unicode version, or null for a value that is not a version.
     *
     * A part of a version past 255, which no part of an age reaches, holds every age that agrees
     * with the version before it, as 255 in that part and those after it does in ICU.
     */
    public function age(string $version): ?Members
    {
        if (preg_match('/\A\d+(\.\d+){0,3}\z/', $version) !== 1) {
            return null;
        }
        $wanted = array_pad(array_map(intval(...), explode('.', $version)), 4, 0);
        $test = static function (int $code) use ($wanted): bool {
            $age = IntlChar::charAge($code);

            return $age !== [0, 0, 0, 0] && $age <= $wanted;
        };
        $parts = [];
        $past = false;
        foreach ($wanted as $part) {
            $past = $past || $part > 255;
            $parts[] = $past ? 255 : $part;
        }

        return $this->listed('a' . $version, $test, '[:Age=' . implode('.', $parts) . ':]');
    }

    /**
     * Answers the set of the characters that pass a test, listed once for each key, from ICU when a UnicodeSet pattern names the same set.
     *
     * @param Closure(int): bool $test
     * @param string|null $set The UnicodeSet pattern of the same set
     */
    public function listed(string $key, Closure $test, ?string $set = null): Members
    {
        self::$listed[$key] ??= (new Listing())->ranges($test, $set);

        return self::$listed[$key] === '' ? new Members([]) : new Members([self::$listed[$key]]);
    }

    /**
     * Writes the UnicodeSet pattern of a binary property, or of a property and one of its values, in the names ICU gives them, or null when ICU names neither.
     */
    public function pattern(int $property, ?int $value = null): ?string
    {
        $name = IntlChar::getPropertyName($property);
        $named = $value === null ? null : IntlChar::getPropertyValueName($property, $value);
        if ($name === '' || ($value !== null && (!is_string($named) || $named === ''))) {
            return null;
        }

        return '[:' . $name . ($named === null ? '' : '=' . $named) . ':]';
    }
}
