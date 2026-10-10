<?php

declare(strict_types=1);

namespace MySqlMemory\Session\State;

use MySqlMemory\Command\Prepared\ParameterBindings;
use MySqlMemory\Typing\Domain;

/**
 * Named SQL preparations, derived parameter domains and session routine lookups.
 *
 * Wire statements keep their identifiers in the connection; both protocols resolve execution
 * through the same temporary domain map, restored after nested calls and failures. Routine
 * identities remain loaded until a dictionary generation changes or the session ends.
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

    /**
     * The dictionary generation of the routine identities already loaded by this session.
     */
    public int $routineGeneration = -1;

    /**
     * @var array<int, true> Loaded routine object identities in the current dictionary generation
     */
    public array $routines = [];
}
