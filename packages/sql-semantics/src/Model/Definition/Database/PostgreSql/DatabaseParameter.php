<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Database\PostgreSql;

/**
 * Names one PostgreSQL database property that CREATE DATABASE or ALTER DATABASE can request.
 * @visibility public
 * @example Reading the SQL spelling of a parameter
 *     \SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter::ConnectionLimit->value // => 'CONNECTION LIMIT'
 *     \SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter::named('lc_ctype') // => \SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter::LcCtype
 */
enum DatabaseParameter: string
{
    case Owner = 'OWNER';
    case Template = 'TEMPLATE';
    case Encoding = 'ENCODING';
    case Strategy = 'STRATEGY';
    case Locale = 'LOCALE';
    case LcCollate = 'LC_COLLATE';
    case LcCtype = 'LC_CTYPE';
    case IcuLocale = 'ICU_LOCALE';
    case IcuRules = 'ICU_RULES';
    case LocaleProvider = 'LOCALE_PROVIDER';
    case BuiltinLocale = 'BUILTIN_LOCALE';
    case CollationVersion = 'COLLATION_VERSION';
    case Tablespace = 'TABLESPACE';
    case AllowConnections = 'ALLOW_CONNECTIONS';
    case ConnectionLimit = 'CONNECTION LIMIT';
    case IsTemplate = 'IS_TEMPLATE';
    case Oid = 'OID';
    case Location = 'LOCATION';

    /**
     * Finds the parameter PostgreSQL recognizes under a lower-case option name, or null for an unknown name.
     */
    public static function named(string $name): ?self
    {
        if ($name !== strtolower($name) || str_contains($name, ' ')) {
            return null;
        }
        return $name === 'connection_limit' ? self::ConnectionLimit : self::tryFrom(strtoupper($name));
    }

    /**
     * Reports whether ALTER DATABASE may change this property in its option form.
     */
    public function alterable(): bool
    {
        return in_array($this, [self::AllowConnections, self::ConnectionLimit, self::IsTemplate], true);
    }

    /**
     * Reports whether a requested value is in the parameter's declared domain; null requests the server default.
     */
    public function accepts(string|int|bool|null $value): bool
    {
        if ($value === null) {
            return true;
        }
        return match ($this) {
            self::AllowConnections, self::IsTemplate => is_bool($value),
            self::ConnectionLimit => (is_int($value) && $value >= -1 && $value <= 2147483647),
            self::Oid => (is_int($value) && $value >= 16384 && $value <= 4294967295),
            self::Encoding => is_int($value) || (is_string($value) && $value !== ''),
            self::Strategy => (is_string($value) && in_array(strtolower($value), ['wal_log', 'file_copy'], true)),
            self::LocaleProvider => (is_string($value) && in_array(strtolower($value), ['builtin', 'icu', 'libc'], true)),
            self::Owner, self::Template, self::Tablespace => (is_string($value) && $value !== ''),
            self::Locale, self::LcCollate, self::LcCtype, self::IcuLocale, self::IcuRules, self::BuiltinLocale, self::CollationVersion, self::Location => is_string($value),
        };
    }
}
