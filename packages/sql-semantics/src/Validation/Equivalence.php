<?php

declare(strict_types=1);

namespace SqlSemantics\Validation;

use ReflectionClass;
use ReflectionProperty;
use UnitEnum;

/**
 * Compares two statement structures operand by operand.
 *
 * Two structures correspond when they have the same classes, the same scalar
 * values and enum cases, and lists of the same length and order, recursively.
 * This is a comparison of actual operands, not of a fingerprint or of text.
 *
 * @visibility SqlSemantics
 */
final class Equivalence
{
    /**
     * @var array<class-string, list<ReflectionProperty>>
     */
    private array $properties = [];

    /**
     * Answers the path of the first difference, or null when the structures correspond.
     */
    public function difference(object $left, object $right): ?string
    {
        $lefts = [$left];
        $rights = [$right];
        $paths = ['statement'];
        while ($paths !== []) {
            $a = array_pop($lefts);
            $b = array_pop($rights);
            $path = array_pop($paths);
            if (is_array($a) && is_array($b)) {
                if (array_keys($a) !== array_keys($b)) {
                    return $path . ' (list of ' . count($a) . ' against ' . count($b) . ')';
                }
                foreach ($a as $key => $item) {
                    $lefts[] = $item;
                    $rights[] = $b[$key];
                    $paths[] = $path . '[' . $key . ']';
                }
                continue;
            }
            if (!is_object($a) || !is_object($b) || $a instanceof UnitEnum || $b instanceof UnitEnum) {
                if ($a !== $b) {
                    return $path . ' (' . $this->describe($a instanceof UnitEnum || is_scalar($a) ? $a : get_debug_type($a)) . ' against ' . $this->describe($b instanceof UnitEnum || is_scalar($b) ? $b : get_debug_type($b)) . ')';
                }
                continue;
            }
            if ($a::class !== $b::class) {
                return $path . ' (' . $a::class . ' against ' . $b::class . ')';
            }
            foreach ($this->members($a) as $property) {
                $lefts[] = $property->getValue($a);
                $rights[] = $property->getValue($b);
                $paths[] = $path . '->' . $property->getName();
            }
        }

        return null;
    }

    /**
     * Answers the properties of a class, cached for one comparison.
     *
     * @return list<ReflectionProperty>
     */
    public function members(object $value): array
    {
        return $this->properties[$value::class] ??= (new ReflectionClass($value))->getProperties();
    }

    /**
     * Describes a compared scalar or enum case in a failure message.
     */
    public function describe(UnitEnum|string|int|float|bool $value): string
    {
        return $value instanceof UnitEnum ? $value::class . '::' . $value->name : var_export($value, true);
    }
}
