<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\TriggerSchemas;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A trigger input whose explicit owner and table databases cannot agree.
 *
 * The target identity remains available for preceding catalog conditions. This
 * is a refused input, not an ordinary trigger declaration, and retains no body,
 * SQL text or parser node.
 *
 * @example Describing contradictory explicit database qualifiers
 *     $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('tr'), new \SqlSemantics\Statement\Identifier\Name('a'));
 *     $table = new \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('b')));
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\TriggerSchemaMismatch($name, $table))->message() // => 'Trigger in wrong schema'
 *
 * @visibility public
 */
final class TriggerSchemaMismatch implements Diagnostic
{
    use Snapshot;

    /**
     * @param QualifiedName $name The explicitly qualified trigger name
     * @param TriggerTable $table The explicitly qualified target table
     * @param Account|null $definer The account checked before catalog lookup
     */
    public function __construct(public readonly QualifiedName $name, public readonly TriggerTable $table, public readonly ?Account $definer = null)
    {
        ProgramNames::check($name);
        Check::input($name->catalog === null, 'A trigger name has at most a database qualifier.');
        Check::input(!TriggerSchemas::compatible($name, $table), 'A trigger-schema refusal requires distinct explicit database qualifiers.');
    }

    /**
     * Describes the contradiction reached after resolving the target table.
     */
    public function message(): string
    {
        return 'Trigger in wrong schema';
    }
}
