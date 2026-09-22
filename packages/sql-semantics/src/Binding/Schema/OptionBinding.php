<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Relation\QualifiedName;

/**
 * Narrows internal parsed option values into their declared domain types.
 *
 * @visibility SqlSemantics
 */
final class OptionBinding
{
    /**
     * @param array<string, string|bool|list<string>> $options
     * @throws UnclassifiedSql
     */
    public static function string(array $options, string $name): ?string
    {
        $value = $options[$name] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new UnclassifiedSql('Expected a scalar declaration option: ' . $name);
        }
        return $value;
    }

    /**
     * @param array<string, string|bool|list<string>> $options
     * @throws UnclassifiedSql
     */
    public static function integer(array $options, string $name): ?int
    {
        $value = self::string($options, $name);
        if ($value === null) {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false) {
            throw new UnclassifiedSql('Expected an integer declaration option: ' . $name);
        }
        return $integer;
    }

    /**
     * @param array<string, string|bool|list<string>> $options
     * @throws UnclassifiedSql
     */
    public static function qualified(array $options, string $name): ?QualifiedName
    {
        $value = $options[$name] ?? null;
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            throw new UnclassifiedSql('Expected an identifier declaration option: ' . $name);
        }
        return new QualifiedName(is_string($value) ? [$value] : $value);
    }

    /**
     * @param array<string, string|bool|list<string>> $options
     * @param list<string> $names
     * @throws UnclassifiedSql
     */
    public static function classified(array $options, array $names): void
    {
        $unknown = array_diff(array_keys($options), $names);
        if ($unknown !== []) {
            throw new UnclassifiedSql('Unclassified declaration options: ' . implode(', ', $unknown));
        }
    }
}
