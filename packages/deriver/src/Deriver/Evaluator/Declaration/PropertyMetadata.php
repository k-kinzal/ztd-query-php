<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Declaration;

use Deriver\Deriver\Evaluator\Term;

/**
 * A property declaration, independent of any object's current state.
 * @visibility root
 * @example Inspecting a declared property
 *     (new \Deriver\Deriver\Evaluator\Declaration\PropertyMetadata("table", "User", "string", \Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility::Protected, false, false, \Deriver\Deriver\Evaluator\Term::constant("users")))->type // => "string"
 */
final class PropertyMetadata
{
    /**
     * @param string $name Property name
     * @param string $className Declaring scope
     * @param string $type Declared type
     * @param \Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility $visibility Declared access
     * @param bool $static Whether storage is shared by the class
     * @param bool $readonly Whether the property is readonly
     * @param Term|null $default Null means no initializer; opaque means an unevaluated initializer
     * @param string $docComment Raw doc comment of the property or promoted parameter, or an empty string
     */
    public function __construct(
        public readonly string $name,
        public readonly string $className,
        public readonly string $type,
        public readonly \Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility $visibility,
        public readonly bool $static,
        public readonly bool $readonly,
        public readonly ?Term $default,
        public readonly string $docComment = '',
    ) {
    }
}
