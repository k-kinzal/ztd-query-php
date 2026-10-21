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
     * The active table preparation's deferred trigger resource notice.
     */
    public ?\MySqlMemory\Hint\TriggerNotices $triggerNotices = null;

    /**
     * Counts actual trigger resource notices loaded during semantic preparation.
     */
    public int $triggerResourceLoads = 0;

    /**
     * A wire preparation's resource notice retained for its immediate execution.
     */
    public bool $replayedTriggerResource = false;

    /**
     * @var array<string, array{string, int, ParameterBindings, \MySqlMemory\Command\Prepared\PreparedCache}> SQL preparations by lower-case name
     */
    public array $named = [];

    /**
     * @var array<int, Domain> Derived domains used to resolve the current execution
     */
    public array $domains = [];

    /**
     * @var WeakMap<\SqlSemantics\Statement\Scalar, \MySqlMemory\Evaluation\Leaf\Constant>|null Conditions evaluated once at modern statement preparation
     */
    public ?WeakMap $conditions = null;

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

    /**
     * @var WeakMap<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables, true>|null Stored inspections whose unresolved labels require another preparation
     */
    public ?WeakMap $inspections = null;

    /**
     * @var WeakMap<\SqlSemantics\Platform\MySql\Statement\Account\CreateRole, array<int, bool>>|null Role authentication initialized by each stored definition operand
     */
    public ?WeakMap $roleCreations = null;

    /**
     * @var WeakMap<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable, array{\MySqlMemory\Dictionary\TableDefinition|\MySqlMemory\Dictionary\View|null}>|null Destination identities observed by stored retained index declarations
     */
    public ?WeakMap $indexCreations = null;
}
