<?php

declare(strict_types=1);

namespace Deriver\Model\Builtin;

use Deriver\Value\Term;

/**
 * Implements registered standard functions over immutable abstract values.
 * @visibility root
 */
final class ScalarFunctions
{
    /**
     * @param int|null $floatPrecision Captured target precision for float-to-string conversion; null when unknown
     */
    public function __construct(public readonly ?int $floatPrecision = null)
    {
    }

    /**
     * Applies a known standard intrinsic without dynamic host-function invocation.
     * @param string $name Registered function
     * @param list<Term> $values Bound arguments
     * @return Term Concrete or symbolic result
     */
    public function apply(string $name, array $values): Term
    {
        if ($name === 'sort-values') {
            return (new Sorting($this->floatPrecision))->apply($values);
        }
        if ($name === 'replace-pair') {
            return (new Replacement())->apply($values);
        }
        if ($name === 'sprintf' || $name === 'vsprintf') {
            return (new Formatting($this->floatPrecision))->apply($values);
        }
        if (in_array($name, ['array_fill', 'str_repeat', 'intval'], true)) {
            return (new ConstructionFunctions())->apply($name, $values);
        }
        $a = $values[0] ?? Term::constant(null);
        if ($name === 'get_class') {
            return $this->className($a);
        }
        if (str_starts_with($name, 'is_')) {
            return (new TypePredicates())->apply($name, $a);
        }
        if (in_array($name, ['count', 'array_keys', 'array_values', 'array_merge', 'array_key_exists', 'in_array'], true)) {
            return (new ArrayFunctions($this->floatPrecision))->apply($name, $values);
        }
        if (in_array($name, ['strlen', 'strtolower', 'strtoupper', 'ucfirst', 'lcfirst', 'trim', 'ltrim', 'rtrim', 'substr', 'implode', 'join', 'explode', 'sprintf', 'str_replace'], true)) {
            return (new StringFunctions($this->floatPrecision))->apply($name, $values);
        }
        return Term::opaque('UNSUPPORTED_MODEL_CASE', dependencies: $values);
    }

    /**
     * Reads an object's exact runtime class without host reflection or autoloading.
     * @param Term $object Object argument already validated by the signature
     * @return Term Exact class, symbolic runtime class, or an explicit omitted-argument boundary
     */
    public function className(Term $object): Term
    {
        if ($object->kind === 'omitted') {
            return Term::opaque('UNSUPPORTED_MODEL_CASE');
        }
        if ($object->kind === 'closure') {
            return Term::constant('Closure', $object->isSecret());
        }
        $class = $object->attributes['class'] ?? null;
        return in_array($object->kind, ['object', 'enum'], true) && is_string($class) ? Term::constant($class, $object->isSecret()) : new Term('intrinsic', 'get_class', [$object], ['type' => 'string']);
    }
}
