<?php

declare(strict_types=1);

namespace Deriver\Model\Metadata;

use Deriver\Value\Term;

/**
 * Class and composed trait facts; inherited members remain on the named parent.
 * @visibility public
 * @example Inspecting captured class identity
 *     (new \Deriver\Model\Metadata\ClassMetadata("User"))->name // => "User"
 */
final class ClassMetadata
{
    /**
     * @param string $name Declaration spelling
     * @param string $parent Parent class, or an empty string
     * @param list<string> $interfaces Declared interfaces
     * @param list<string> $traits Direct trait uses
     * @param array<string, string> $methods Lowercase method names mapped to implementation identities
     * @param array<string, PropertyMetadata> $properties Declared and composed properties
     * @param array<string, Term> $constants Captured constant values; unresolved initializers stay explicit
     * @param bool $final Whether subclasses are forbidden
     * @param bool $abstract Whether the class is abstract
     * @param bool $interface Whether this is an interface
     * @param bool $enum Whether this is an enum
     */
    public function __construct(
        public readonly string $name,
        public readonly string $parent = '',
        public readonly array $interfaces = [],
        public readonly array $traits = [],
        public readonly array $methods = [],
        public readonly array $properties = [],
        public readonly array $constants = [],
        public readonly bool $final = false,
        public readonly bool $abstract = false,
        public readonly bool $interface = false,
        public readonly bool $enum = false,
    ) {
    }
}
