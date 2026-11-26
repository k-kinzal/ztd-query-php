<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Problem;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A command that names a relation of a kind it does not accept, as the declaration of the relation states it.
 *
 * @visibility SqlSemantics
 * @example Refusing a problem without the relation its message names
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindProblem(\SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule::NotView, null) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 * @example Refusing an action for a rule that names none
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindProblem(\SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule::NotView, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t'), \SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\AlterAction::AddColumn) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class KindProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param KindRule $rule The broken rule
     * @param Name|null $relation The relation the command names, for the rules whose message names it
     * @param AlterAction|null $action The ALTER action, for the rule that names one
     */
    public function __construct(public readonly KindRule $rule, public readonly ?Name $relation, public readonly ?AlterAction $action = null)
    {
        Check::input(($relation !== null) === $rule->namesRelation(), 'Exactly the rules whose message names the relation name it.');
        Check::input(($action !== null) === ($rule === KindRule::AlterAction), 'Exactly the ALTER action rule names an action.');
    }

    /**
     * Describes the broken rule in the words of PostgreSQL.
     */
    public function message(): string
    {
        return sprintf($this->rule->value, $this->relation->value ?? '', $this->action->value ?? '');
    }
}
