<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Definition;

use SqlSemantics\Model\Definition\Routine\ColumnTypeReference;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Rules shared by definition lists: the attribute family, unique attributes, required attributes, and present arguments.
 * @visibility SqlSemantics
 */
final class DefinitionOptions
{
    /**
     * Every option names a distinct attribute from the allowed set.
     * @param list<DefinitionOption> $options
     * @param list<DefinitionAttribute> $allowed
     * @throws InvalidStructure
     */
    public static function validate(array $options, array $allowed): void
    {
        Collections::objects($options, DefinitionOption::class);
        $seen = [];
        foreach ($options as $option) {
            if (!in_array($option->attribute, $allowed, true) || in_array($option->attribute, $seen, true)) {
                throw new InvalidStructure('A definition names each allowed attribute at most once: ' . $option->attribute->spelling());
            }
            $seen[] = $option->attribute;
        }
    }

    /**
     * The listed attributes are present.
     * @param list<DefinitionOption> $options
     * @param list<DefinitionAttribute> $required
     * @throws InvalidStructure
     */
    public static function require(array $options, array $required): void
    {
        foreach ($required as $attribute) {
            if (self::find($options, $attribute) === null) {
                throw new InvalidStructure('The definition requires the ' . $attribute->spelling() . ' attribute.');
            }
        }
    }

    /**
     * Every option has an argument.
     * @param list<DefinitionOption> $options
     * @throws InvalidStructure
     */
    public static function present(array $options): void
    {
        foreach ($options as $option) {
            if ($option->value === null) {
                throw new InvalidStructure('The ' . $option->attribute->spelling() . ' attribute requires an argument.');
            }
        }
    }

    /**
     * The option for an attribute, when present.
     * @param list<DefinitionOption> $options
     */
    public static function find(array $options, DefinitionAttribute $attribute): ?DefinitionOption
    {
        foreach ($options as $option) {
            if ($option->attribute === $attribute) {
                return $option;
            }
        }
        return null;
    }

    /**
     * The argument of an attribute, or null when the attribute is absent.
     * @param list<DefinitionOption> $options
     */
    public static function value(array $options, DefinitionAttribute $attribute): QualifiedName|TypeDescriptor|ColumnTypeReference|bool|int|string|null
    {
        return self::find($options, $attribute)?->value;
    }
}
