<?php

declare(strict_types=1);

namespace SqlCatalog\Php;

use PhpParser\Node\Expr;
use SqlCatalog\Type\TypeShape;

/**
 * One class, interface, trait or enum, reduced to the parts the analyzer reads.
 *
 * @visibility root
 */
final class ClassShape
{
    /**
     * @param string $name The declaration, fully qualified without a leading backslash
     * @param string|null $parent The parent class, when one is extended
     * @param list<string> $interfaces The implemented interfaces and extended interfaces
     * @param list<string> $traits The used traits
     * @param bool $enum Whether the declaration is an enum
     * @param array<string, Expr> $constants Class constant expressions, keyed by name
     * @param array<string, Expr|null> $enumCases Enum case backing expressions, keyed by case name
     * @param array<string, TypeShape> $propertyTypes Declared property types, keyed by property name
     * @param array<string, MethodShape> $methods Methods, keyed by lower-case name
     * @param array<string, Expr> $propertyDefaults Property default expressions, keyed by property name
     * @param bool $hasAttributes Whether attributes may customize runtime behavior
     * @param array<string, true> $assignedProperties Properties the class assigns to somewhere in its body
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $parent,
        public readonly array $interfaces,
        public readonly array $traits,
        public readonly bool $enum,
        public readonly array $constants,
        public readonly array $enumCases,
        public readonly array $propertyTypes,
        public readonly array $methods,
        public readonly array $propertyDefaults = [],
        public readonly array $assignedProperties = [],
        public readonly bool $hasAttributes = false,
    ) {
    }

    /**
     * The default expression of a property that nothing in the class overwrites.
     *
     * A property declared with a default and never assigned again holds that
     * default for the whole life of every instance, which is what lets a table
     * name kept in a property resolve instead of becoming a gap.
     */
    public function settledDefault(string $property): ?Expr
    {
        if (isset($this->assignedProperties[$property])) {
            return null;
        }

        return $this->propertyDefaults[$property] ?? null;
    }

    /**
     * The names this declaration inherits from, nearest first.
     *
     * @return list<string>
     */
    public function ancestors(): array
    {
        $names = $this->parent === null ? [] : [$this->parent];

        return array_merge($names, $this->traits, $this->interfaces);
    }
}
