<?php

declare(strict_types=1);

namespace Deriver\Project;

use Deriver\Exception\InvalidInputException;
use Deriver\Value\Term;

/**
 * A declared application entry and its explicitly supplied initial inputs.
 *
 * Properties give the receiver object its initial state. Each key names a declared instance
 * property as seen from the entry method's class; unspecified properties stay symbolic values
 * of their declared types, and a supplied value must satisfy the declared type.
 *
 * @visibility public
 * @example Declaring an entry
 *     (new \Deriver\Project\EntryPoint('App\\run'))->symbol // => 'App\\run'
 * @example Supplying the receiver's initial property values
 *     $entry = new \Deriver\Project\EntryPoint('App\\Repository::find', properties: ['order' => \Deriver\Value\Term::constant('name')]);
 *     $entry->properties['order']->native() // => 'name'
 */
final class EntryPoint
{
    /**
     * @param string $symbol Fully qualified callable or script identity
     * @param array<int|string, Term> $arguments Positional or named initial values
     * @param Term|null $receiver Initial receiver identity, if supplied
     * @param array<string, Term> $properties Initial receiver property values keyed by property name
     * @throws InvalidInputException If a property key is not a PHP property name
     */
    public function __construct(
        public readonly string $symbol,
        public readonly array $arguments = [],
        public readonly ?Term $receiver = null,
        public readonly array $properties = [],
    ) {
        foreach (array_keys($properties) as $name) {
            if (preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/D', strval($name)) !== 1) {
                throw new InvalidInputException('Entry property names must be PHP property names: ' . $name);
            }
        }
    }
}
