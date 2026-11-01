<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Dml\TargetNames;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A DELETE target prefix ending at its first intrinsically repeated name.
 *
 * The prefix retains earlier default-database dependencies. The enclosing
 * declaration supplies its own database when the refusal is in a stored body.
 * No input SQL or parser node is retained.
 *
 * @visibility public
 * @example Refusing a diagnostic without a repeated target
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget([new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class RepeatedDeleteTarget implements Diagnostic
{
    use Snapshot;

    /**
     * @var non-empty-list<QualifiedName> The prefix through the first repeated target
     */
    public readonly array $targets;

    /**
     * @param list<QualifiedName> $targets The target prefix through its first repetition
     * @param QualifiedName|null $program The enclosing stored declaration
     */
    public function __construct(array $targets, public readonly ?QualifiedName $program = null)
    {
        $this->targets = Check::listOf($targets, QualifiedName::class, 'A repeated DELETE target retains its nonempty prefix.', 1);
        Check::input((new TargetNames())->duplicate($this->targets) === count($this->targets) - 1, 'A refused DELETE prefix ends at its first repeated target.');
        foreach ($this->targets as $target) {
            Check::input($target->catalog === null, 'A table is qualified by at most a database.');
        }
    }

    /**
     * Describes the repeated target; earlier database dependencies are contextual.
     */
    public function message(): string
    {
        return "Not unique table/alias: '" . $this->targets[count($this->targets) - 1]->name->value . "'";
    }
}
