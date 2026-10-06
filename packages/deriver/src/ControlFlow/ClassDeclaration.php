<?php

declare(strict_types=1);

namespace Deriver\ControlFlow;

use Deriver\Value\Term;

/**
 * A static class declaration; it is never instantiated by the analyzer.
 *
 * @visibility root
 */
final class ClassDeclaration
{
    /**
     * @param string $name name
     * @param string $parent parent
     * @param list<string> $interfaces interfaces
     * @param list<string> $traits traits
     * @param array<string, string> $methods methods
     * @param array<string, PropertyDeclaration> $properties properties
     * @param array<string, Term> $constants constants
     * @param bool $final final
     * @param bool $abstract abstract
     * @param bool $interface interface
     * @param bool $readonly Whether dynamic properties and subsequent writes are forbidden
     * @param bool $composed Whether trait members are already imported into this class
     * @param array<string, ClassConstant> $constantDeclarations Constant access and initializer contracts
     * @param bool $enum Whether allocation is restricted to declared enum cases
     * @param string $docComment Raw declaration doc comment, or an empty string
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
        public readonly bool $readonly = false,
        public readonly bool $enum = false,
        public readonly bool $composed = false,
        public readonly array $constantDeclarations = [],
        public readonly string $docComment = '',
    ) {
    }
}
