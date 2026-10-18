<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Request;

use Deriver\Exception\InvalidInputException;

/**
 * A declared application entry and its explicitly supplied initial inputs.
 *
 * Properties give the receiver object its initial state. Each key names a declared instance
 * property as seen from the entry method's class; unspecified properties stay symbolic values
 * of their declared types, and a supplied value must satisfy the declared type.
 *
 * @visibility root
 * @example Declaring an entry
 *     (new \Deriver\Deriver\Evaluator\Request\EntryPoint('App\\run'))->symbol // => 'App\\run'
 * @example Supplying the receiver's initial property values
 *     $entry = new \Deriver\Deriver\Evaluator\Request\EntryPoint('App\\Repository::find', properties: ['order' => \Deriver\Deriver\Evaluator\Term::constant('name')]);
 *     $entry->properties['order']->native() // => 'name'
 */
final class EntryPoint
{
    /**
     * @param string $symbol Fully qualified callable or script identity
     * @param array<int|string, \Deriver\Deriver\Evaluator\Term> $arguments Positional or named initial values
     * @param \Deriver\Deriver\Evaluator\Term|null $receiver Initial receiver identity, if supplied
     * @param array<string, \Deriver\Deriver\Evaluator\Term> $properties Initial receiver property values keyed by property name
     * @param array<string, \Deriver\Deriver\Evaluator\Term> $captures Explicit lexical captures for closure entries; omitted captures remain symbolic and undeclared names are rejected at derivation
     * @param bool $symbolicArguments Whether omitted parameters range over all valid inputs, including enum cases
     * @throws InvalidInputException If a property key is not a PHP property name
     */
    public function __construct(
        public readonly string $symbol,
        public readonly array $arguments = [],
        public readonly ?\Deriver\Deriver\Evaluator\Term $receiver = null,
        public readonly array $properties = [],
        public readonly array $captures = [],
        public readonly bool $symbolicArguments = false,
    ) {
        foreach (array_keys($properties) as $name) {
            if (preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/D', strval($name)) !== 1) {
                throw new InvalidInputException('Entry property names must be PHP property names: ' . $name);
            }
        }
    }
}
