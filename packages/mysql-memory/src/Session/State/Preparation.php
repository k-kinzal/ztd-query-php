<?php

declare(strict_types=1);

namespace MySqlMemory\Session\State;

use MySqlMemory\Command\Prepared\ParameterBindings;
use MySqlMemory\Typing\Domain;
use WeakMap;

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
     * Whether the input currently being interpreted belongs to prepared execution.
     * Scoped by Session::analyze and restored on both success and failure.
     */
    public bool $inputPrepared = false;

    /**
     * @var array<string, array{string, int, ParameterBindings, \MySqlMemory\Command\Prepared\PreparedCache}> SQL preparations by lower-case name
     */
    public array $named = [];

    /**
     * @var array<int, Domain> Derived domains used to resolve the current execution
     */
    public array $domains = [];

    /**
     * The current operation's resolved typing context, scoped to command execution.
     */
    public ?\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings $resolution = null;

    /**
     * The dictionary generation of the routine identities already loaded by this session.
     */
    public int $routineGeneration = -1;

    /**
     * @var array<int, true> Loaded routine object identities in the current dictionary generation
     */
    public array $routines = [];
    /**
     * @var WeakMap<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection, array{list<\SqlSemantics\Statement\Declaration\Table|null>, bool}>|null Stored selection relation snapshots and pending operand retries
     */
    public ?WeakMap $selections = null;

}
