<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Problem;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A definition of a table, index, view, sequence, trigger, rule or policy that PostgreSQL rejects.
 *
 * @visibility SqlSemantics
 * @example Refusing a relation for a rule that names none
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem(\SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule::DuplicateColumn, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class DefinitionProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param DefinitionRule $rule The broken rule
     * @param Name|null $subject The name the problem is about, for the rules that name one
     * @param Name|null $relation The relation of the subject, for the rules that name both
     */
    public function __construct(public readonly DefinitionRule $rule, public readonly ?Name $subject = null, public readonly ?Name $relation = null)
    {
        Check::input($relation === null || ($subject !== null && substr_count($rule->value, '%s') === 2), 'Only a rule that names a subject and its relation takes a relation.');
    }

    /**
     * Describes the broken rule in the words of PostgreSQL.
     */
    public function message(): string
    {
        if ($this->relation !== null) {
            return sprintf($this->rule->value, $this->subject->value ?? '', $this->relation->value);
        }

        return str_replace('%s', $this->subject->value ?? '', $this->rule->value);
    }
}
