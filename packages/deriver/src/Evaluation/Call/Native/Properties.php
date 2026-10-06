<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Native;

use Deriver\ControlFlow\Program;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Term;

/**
 * Keeps native throwable properties in the same heap as source object state.
 * @visibility root
 */
final class Properties
{
    /**
     * @param Program $program Source inheritance hierarchy
     */
    public function __construct(public readonly Program $program)
    {
    }

    /**
     * Resolves native property visibility and type before ordinary heap access.
     * @param string $class Native declaring class or subclass
     * @param string $name Property spelling
     * @return PropertyDeclaration|null Known native property
     */
    public function find(string $class, string $name): ?PropertyDeclaration
    {
        $family = (new Signatures($this->program))->family($class);
        if ($family === '') {
            return null;
        }
        if ($family === 'ErrorException' && $name === 'severity') {
            return new PropertyDeclaration('severity', 'ErrorException', 'int', 'protected');
        }
        $base = $family === 'Error' ? 'Error' : 'Exception';
        return match ($name) {
            'message', 'code' => new PropertyDeclaration($name, $base, 'mixed', 'protected'),
            'file' => new PropertyDeclaration($name, $base, 'string', 'protected'),
            'line' => new PropertyDeclaration($name, $base, 'int', 'protected'),
            'previous' => new PropertyDeclaration($name, $base, 'Throwable|null', 'private'),
            'trace' => new PropertyDeclaration($name, $base, 'array', 'private'),
            default => null,
        };
    }

    /**
     * Initializes inherited internal storage before source property initializers.
     * @param string $class Native class in the inheritance walk
     * @param Term $object Allocated identity
     * @param State $state Mutable allocation path
     * @return void
     */
    public function initialize(string $class, Term $object, State $state): void
    {
        $family = (new Signatures($this->program))->family($class);
        if ($family === '') {
            return;
        }
        $base = $family === 'Error' ? 'Error' : 'Exception';
        $defaults = ['message' => Term::constant(''), 'code' => Term::constant(0), 'file' => Term::opaque('RUNTIME_STACK', 'string'), 'line' => Term::opaque('RUNTIME_STACK', 'int'), $base . '::previous' => Term::constant(null), $base . '::trace' => Term::opaque('RUNTIME_STACK', 'array')];
        if ($family === 'ErrorException') {
            $defaults['severity'] = Term::constant(1);
        }
        foreach ($defaults as $name => $value) {
            $address = new Location('object:' . $object->literal, [$name]);
            $state->memory->write($address, $value);
            $state->memory->propertyTypes[$address->root][$name] = $this->find($class, explode('::', $name)[1] ?? $name)->type ?? 'mixed';
        }
    }

    /**
     * Selects the heap slot read by each supported final native getter.
     * @param string $family Native constructor family
     * @param string $method Canonical lowercase method
     * @return string|null Property slot or no supported getter
     */
    public function getter(string $family, string $method): ?string
    {
        return match ($method) {
            'getmessage' => 'message', 'getcode' => 'code', 'getfile' => 'file', 'getline' => 'line',
            'getprevious' => ($family === 'Error' ? 'Error' : 'Exception') . '::previous',
            'getseverity' => $family === 'ErrorException' ? 'severity' : null,
            default => null,
        };
    }
}
