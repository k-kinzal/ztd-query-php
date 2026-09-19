<?php

declare(strict_types=1);

namespace SqlCatalog\Type;

/**
 * A PHP static type, flattened into the set of alternatives a value may take.
 *
 * Member names are the lower-case builtin names (`int`, `float`, `string`,
 * `bool`, `null`, `array`, `object`, `callable`, `iterable`, `mixed`) or a
 * fully qualified class name written without its leading backslash.
 *
 * @visibility root
 */
final class TypeShape
{
    private const BUILTINS = [
        'int' => true,
        'float' => true,
        'string' => true,
        'bool' => true,
        'null' => true,
        'array' => true,
        'object' => true,
        'callable' => true,
        'iterable' => true,
        'mixed' => true,
        'void' => true,
        'never' => true,
    ];

    /**
     * @param list<string> $names Alternatives, already normalized and sorted
     */
    private function __construct(public readonly array $names)
    {
    }

    /**
     * The type that admits every value, used whenever nothing is known.
     */
    public static function unknown(): self
    {
        return new self(['mixed']);
    }

    /**
     * A type built from one or more alternative names.
     *
     * @param list<string> $names
     */
    public static function of(array $names): self
    {
        $normalized = [];
        foreach ($names as $name) {
            $member = self::normalize($name);
            if ($member === 'mixed') {
                return new self(['mixed']);
            }
            $normalized[$member] = true;
        }

        if ($normalized === []) {
            return self::unknown();
        }

        $members = array_keys($normalized);
        sort($members);

        return new self($members);
    }

    /**
     * Normalizes one written type name to its member form.
     */
    public static function normalize(string $name): string
    {
        $trimmed = ltrim(trim($name), '\\');
        $lower = strtolower($trimmed);

        if ($lower === 'boolean') {
            return 'bool';
        }
        if ($lower === 'integer') {
            return 'int';
        }
        if ($lower === 'double') {
            return 'float';
        }
        if ($lower === 'false' || $lower === 'true') {
            return 'bool';
        }
        if (isset(self::BUILTINS[$lower])) {
            return $lower;
        }

        return $trimmed;
    }

    /**
     * Whether nothing is known about the values of this type.
     */
    public function isUnknown(): bool
    {
        return $this->names === ['mixed'];
    }

    /**
     * Whether `null` is one of the alternatives.
     */
    public function isNullable(): bool
    {
        return in_array('null', $this->names, true);
    }

    /**
     * The class names among the alternatives, in declaration order.
     *
     * @return list<string>
     */
    public function classNames(): array
    {
        $classes = [];
        foreach ($this->names as $name) {
            if (!isset(self::BUILTINS[$name])) {
                $classes[] = $name;
            }
        }

        return $classes;
    }

    /**
     * The single class name this type denotes, or null when it denotes none or several.
     */
    public function soleClassName(): ?string
    {
        $classes = $this->classNames();
        $withoutNull = array_values(array_filter($this->names, static fn (string $name): bool => $name !== 'null'));

        return count($classes) === 1 && count($withoutNull) === 1 ? $classes[0] : null;
    }

    /**
     * A type admitting every value either of the two admits.
     */
    public function union(self $other): self
    {
        return self::of(array_merge($this->names, $other->names));
    }

    /**
     * Drops `null` from the alternatives.
     */
    public function withoutNull(): self
    {
        $names = array_values(array_filter($this->names, static fn (string $name): bool => $name !== 'null'));

        return $names === [] ? self::unknown() : self::of($names);
    }

    /**
     * The type written the way PHP writes it.
     */
    public function display(): string
    {
        return implode('|', $this->names);
    }
}
