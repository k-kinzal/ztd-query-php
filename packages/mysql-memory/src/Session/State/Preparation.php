<?php

declare(strict_types=1);

namespace MySqlMemory\Session\State;

use MySqlMemory\Command\Prepared\ParameterBindings;
use MySqlMemory\Typing\Domain;

/**
 * Named SQL preparations and the derived parameter domains of the current execution.
 *
 * Wire statements keep their identifiers in the connection; both protocols resolve execution
 * through the same temporary domain map, restored after nested calls and failures.
 *
 * @visibility MySqlMemory
 */
final class Preparation
{
    /**
     * @var array<string, array{string, int, ParameterBindings}> SQL preparations by lower-case name
     */
    public array $named = [];

    /**
     * @var array<int, Domain> Derived domains used to resolve the current execution
     */
    public array $domains = [];
}
