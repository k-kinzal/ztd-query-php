<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Platform\MySql\Rules\ColumnResolver;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * What names mean at one position of a stored program.
 *
 * Rule: MYSQL-PROGRAM-SCOPE-001. The scope holds the environment in which
 * the parameters and local variables are visible as rows (the innermost
 * block first, so an inner declaration hides an outer one), the action time
 * and event of a trigger, the labels of
 * the enclosing blocks and loops, and the conditions and cursors declared
 * around the position, the innermost last. Names of variables, labels,
 * conditions and cursors are compared without regard to letter case. It is
 * a working value of one derivation. Terminates: every search is over a
 * finite list. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/local-variable-scope.html,
 * https://dev.mysql.com/doc/refman/8.4/en/statement-labels.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ProgramScope
{
    /**
     * @param Environment $environment The environment the variables are visible in
     * @param ProgramKind|null $kind The kind of program the position belongs to; null outside a program
     * @param list<Name> $blocks The labels of the enclosing blocks
     * @param list<Name> $loops The labels of the enclosing loops
     * @param list<ConditionDeclaration> $conditions The conditions in scope, the innermost last
     * @param list<Name> $cursors The cursors in scope
     * @param TriggerTime|null $time The action time of the trigger the position belongs to; null outside a trigger
     * @param TriggerEvent|null $event The event of the trigger the position belongs to; null outside a trigger
     */
    public function __construct(
        public readonly Environment $environment,
        public readonly ?ProgramKind $kind = null,
        public readonly array $blocks = [],
        public readonly array $loops = [],
        public readonly array $conditions = [],
        public readonly array $cursors = [],
        public readonly ?TriggerTime $time = null,
        public readonly ?TriggerEvent $event = null,
    ) {
    }

    /**
     * Tells whether a list of names holds a name, compared without regard to letter case.
     *
     * @param list<Name> $names
     */
    public function holds(array $names, Name $name): bool
    {
        foreach ($names as $candidate) {
            if (strcasecmp($candidate->value, $name->value) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether a parameter or local variable with the name is in scope.
     */
    public function variable(Name $name): bool
    {
        return (new ColumnResolver())->find($this->environment, $name) instanceof ResolvedColumn;
    }

    /**
     * Finds the innermost condition declared with a name.
     */
    public function condition(Name $name): ?ConditionDeclaration
    {
        for ($index = count($this->conditions) - 1; $index >= 0; $index--) {
            if (strcasecmp($this->conditions[$index]->name->value, $name->value) === 0) {
                return $this->conditions[$index];
            }
        }

        return null;
    }

    /**
     * Answers the scope inside a block or loop with a label; without a label the scope is unchanged.
     */
    public function labeled(?Name $label, bool $loop): self
    {
        if ($label === null) {
            return $this;
        }

        return new self($this->environment, $this->kind, $loop ? $this->blocks : [...$this->blocks, $label], $loop ? [...$this->loops, $label] : $this->loops, $this->conditions, $this->cursors, $this->time, $this->event);
    }

    /**
     * Answers the scope after declarations: another environment, and the conditions and cursors now in scope.
     *
     * @param list<ConditionDeclaration> $conditions
     * @param list<Name> $cursors
     */
    public function declared(Environment $environment, array $conditions, array $cursors): self
    {
        return new self($environment, $this->kind, $this->blocks, $this->loops, $conditions, $cursors, $this->time, $this->event);
    }

    /**
     * Answers the scope of the statement of a handler, which sees no label outside the handler.
     */
    public function handler(): self
    {
        return new self($this->environment, $this->kind, [], [], $this->conditions, $this->cursors, $this->time, $this->event);
    }
}
