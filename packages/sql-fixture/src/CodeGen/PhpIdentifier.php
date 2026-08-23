<?php

declare(strict_types=1);

namespace SqlFixture\CodeGen;

/**
 * Turns SQL names into PHP ones.
 *
 * Plurals are left alone. Guessing that `data` is a plural of `datum`, or that
 * `status` should lose its s, produces names nobody can predict from the
 * schema, and the point of generating them is that they are predictable.
 */
final class PhpIdentifier
{
    private const RESERVED = [
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'declare', 'default', 'do',
        'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach',
        'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends',
        'final', 'finally', 'fn', 'for', 'foreach', 'function', 'global',
        'goto', 'if', 'implements', 'include', 'instanceof', 'insteadof',
        'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or',
        'print', 'private', 'protected', 'public', 'readonly', 'require',
        'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use',
        'var', 'while', 'xor', 'yield',
    ];

    /**
     * Words PHP will not accept as a class name, whatever their case.
     */
    private const RESERVED_CLASS_NAMES = [
        'bool', 'class', 'enum', 'false', 'float', 'int', 'interface',
        'iterable', 'mixed', 'never', 'null', 'object', 'parent', 'self',
        'static', 'string', 'trait', 'true', 'void',
    ];

    /**
     * `order_detail` becomes `OrderDetail`.
     */
    public function className(string $tableName): string
    {
        $words = $this->words($tableName);
        $name = implode('', array_map(ucfirst(...), $words));

        if (in_array(strtolower($name), self::RESERVED_CLASS_NAMES, true)) {
            return $name . 'Table';
        }

        return $this->escapeLeadingDigit($name);
    }

    /**
     * `order_id` becomes `orderId`, and a reserved word gains a suffix so the
     * generated code parses.
     */
    public function parameterName(string $columnName): string
    {
        $words = $this->words($columnName);
        $name = $words[0] . implode('', array_map(ucfirst(...), array_slice($words, 1)));

        if (in_array(strtolower($name), self::RESERVED, true)) {
            return $name . 'Value';
        }

        return $this->escapeLeadingDigit($name);
    }

    /**
     * `order_id` becomes `ORDER_ID`.
     */
    public function constantName(string $columnName): string
    {
        return $this->escapeLeadingDigit(strtoupper(implode('_', $this->words($columnName))));
    }

    /**
     * PHP identifiers cannot start with a digit, but table names can.
     */
    private function escapeLeadingDigit(string $name): string
    {
        return preg_match('/^\d/', $name) === 1 ? '_' . $name : $name;
    }

    /**
     * @return list<string>
     */
    private function words(string $name): array
    {
        $normalized = preg_replace('/[^A-Za-z0-9]+/', ' ', $name) ?? '';
        $normalized = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $normalized) ?? '';

        $words = array_values(array_filter(
            array_map(
                static fn (string $word): string => strtolower($word),
                explode(' ', $normalized)
            ),
            static fn (string $word): bool => $word !== ''
        ));

        return $words === [] ? ['column'] : $words;
    }
}
