<?php

declare(strict_types=1);

namespace Deriver\Model\Builtin;

use Deriver\Model\CallModel;
use Deriver\Model\Signature\Parameter;
use Deriver\Value\Term;

/**
 * Registers explicitly supported standard-library signatures through the public SDK.
 * @visibility root
 */
final class Library
{
    /**
     * Resolves a supported PHP built-in to its declarative model.
     * @param string $symbol Function name
     * @return CallModel|null Standard model
     */
    public function model(string $symbol): ?CallModel
    {
        $name = strtolower(ltrim($symbol, '\\'));
        $parameters = $this->parameters($name);
        return $parameters === null ? null : new FunctionModel($name, $parameters);
    }

    /**
     * Supplies target PHP parameter names, defaults, and reference modes.
     * @param string $name Built-in name
     * @return list<Parameter>|null Supported signature
     */
    public function parameters(string $name): ?array
    {
        $null = Term::constant(null);
        return match ($name) {
            'strlen', 'strtolower', 'strtoupper', 'ucfirst', 'lcfirst' => [new Parameter('string', 'string')],
            'trim', 'ltrim', 'rtrim' => [new Parameter('string', 'string'), new Parameter('characters', 'string', default: Term::constant(" \n\r\t\v\0"))],
            'implode', 'join' => [new Parameter('separator', 'array|string'), new Parameter('array', 'array|null', default: $null)],
            'explode' => [new Parameter('separator', 'string'), new Parameter('string', 'string'), new Parameter('limit', 'int', default: Term::constant(9223372036854775807))],
            'substr' => [new Parameter('string', 'string'), new Parameter('offset', 'int'), new Parameter('length', 'int|null', default: $null)],
            'sprintf' => [new Parameter('format', 'string'), new Parameter('values', 'mixed', variadic: true)],
            'str_replace' => [new Parameter('search', 'array|string'), new Parameter('replace', 'array|string'), new Parameter('subject', 'array|string'), new Parameter('count', 'mixed', byReference: true, default: $null)],
            'getenv' => [new Parameter('name', 'string|null', default: $null), new Parameter('local_only', 'bool', default: Term::constant(false))],
            'random_int' => [new Parameter('min', 'int'), new Parameter('max', 'int')],
            'mt_rand', 'rand' => [new Parameter('min', 'int', default: new Term('omitted')), new Parameter('max', 'int', default: new Term('omitted'))],
            'time' => [],
            'define' => [new Parameter('constant_name', 'string'), new Parameter('value'), new Parameter('case_insensitive', 'bool', default: Term::constant(false))],
            'get_class' => [new Parameter('object', 'object', default: new Term('omitted'))],
            'microtime' => [new Parameter('as_float', 'bool', default: Term::constant(false))],
            'is_array', 'is_string', 'is_int', 'is_integer', 'is_float', 'is_double', 'is_bool', 'is_null', 'is_object', 'is_numeric', 'is_scalar' => [new Parameter('value')],
            'is_callable' => [new Parameter('value'), new Parameter('syntax_only', 'bool', default: Term::constant(false)), new Parameter('callable_name', byReference: true, default: new Term('omitted'))],
            default => $this->valueFunctions($name),
        };
    }

    /**
     * Supplies signatures for deterministic construction helpers.
     * @param string $name Built-in name
     * @return list<Parameter>|null Supported signature
     */
    public function valueFunctions(string $name): ?array
    {
        return match ($name) {
            'str_repeat' => [new Parameter('string', 'string'), new Parameter('times', 'int')],
            'strval' => [new Parameter('value')],
            'intval' => [new Parameter('value'), new Parameter('base', 'int', default: Term::constant(10))],
            'vsprintf' => [new Parameter('format', 'string'), new Parameter('values', 'array')],
            default => $this->arrays($name),
        };
    }

    /**
     * Supplies signatures for collection operations and callback effects.
     * @param string $name Built-in name
     * @return list<Parameter>|null Supported array signature
     */
    public function arrays(string $name): ?array
    {
        $null = Term::constant(null);
        return match ($name) {
            'count' => [new Parameter('value', 'array|Countable'), new Parameter('mode', 'int', default: Term::constant(0))],
            'array_fill' => [new Parameter('start_index', 'int'), new Parameter('count', 'int'), new Parameter('value')],
            'array_values' => [new Parameter('array', 'array')],
            'array_keys' => [new Parameter('array', 'array'), new Parameter('filter_value', default: new Term('omitted')), new Parameter('strict', 'bool', default: Term::constant(false))],
            'array_merge' => [new Parameter('arrays', 'array', variadic: true)],
            'in_array' => [new Parameter('needle'), new Parameter('haystack', 'array'), new Parameter('strict', 'bool', default: Term::constant(false))],
            'array_key_exists' => [new Parameter('key'), new Parameter('array', 'array')],
            'array_map' => [new Parameter('callback', 'callable|null'), new Parameter('array', 'array'), new Parameter('arrays', 'array', variadic: true)],
            'array_filter' => [new Parameter('array', 'array'), new Parameter('callback', 'callable|null', default: $null), new Parameter('mode', 'int', default: Term::constant(0))],
            'array_reduce' => [new Parameter('array', 'array'), new Parameter('callback', 'callable'), new Parameter('initial', default: $null)],
            'call_user_func' => [new Parameter('callback', 'callable'), new Parameter('args', variadic: true)],
            'call_user_func_array' => [new Parameter('callback', 'callable'), new Parameter('args', 'array')],
            'usort' => [new Parameter('array', 'array', byReference: true), new Parameter('callback', 'callable')],
            'sort' => [new Parameter('array', 'array', byReference: true), new Parameter('flags', 'int', default: Term::constant(0))],
            'array_shift', 'array_pop' => [new Parameter('array', 'array', byReference: true)],
            'array_push', 'array_unshift' => [new Parameter('array', 'array', byReference: true), new Parameter('values', 'mixed', variadic: true)],
            'array_key_first', 'array_key_last' => [new Parameter('array', 'array')],
            'array_slice' => [new Parameter('array', 'array'), new Parameter('offset', 'int'), new Parameter('length', 'int|null', default: $null), new Parameter('preserve_keys', 'bool', default: Term::constant(false))],
            default => null,
        };
    }
}
