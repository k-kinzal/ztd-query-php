<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\AttributeArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\AttributeChange;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorChangeAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblemKind;

/**
 * Checks the attributes of `ALTER OPERATOR ... SET` and `ALTER TYPE ... SET`.
 *
 * Rule: PG-ALTER-ATTRIBUTE-001. Both commands read each attribute in turn
 * and stop at the first they refuse: an attribute they do not recognize is
 * an error (not the warning of CREATE OPERATOR and CREATE TYPE); an
 * attribute of the CREATE command that cannot be changed is an error (for an
 * operator `leftarg`, `rightarg`, `function`, `procedure`, and before
 * PostgreSQL 17 also `commutator`, `negator`, `merges` and `hashes`; for a
 * type every attribute but `storage`, `receive`, `send`, `typmod_in`,
 * `typmod_out`, `analyze` and `subscript`). Without a value a function
 * attribute removes the function and a Boolean is true, while an operator
 * attribute and `storage` require a parameter; a value the reading rejects
 * draws the message of PG-DEFINE-ATTRIBUTE-001.
 * Source: https://www.postgresql.org/docs/17/sql-alteroperator.html, https://www.postgresql.org/docs/17/sql-altertype.html,
 * `AlterOperator` in `src/backend/commands/operatorcmds.c` and `AlterType` in `src/backend/commands/typecmds.c` of PostgreSQL 16 and 17.
 * Termination: one pass over the attributes. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ChangeChecks
{
    /**
     * Reports the problem of each changed attribute; `$type` tells ALTER TYPE from ALTER OPERATOR.
     *
     * @param list<AttributeChange> $changes
     */
    public function derive(Derivation $derivation, array $changes, bool $type): void
    {
        foreach ($changes as $change) {
            $problem = $this->problem($change->attribute, $type, $derivation->context->profile->grammar);
            if ($problem !== null) {
                $derivation->report($problem);
            }
        }
    }

    /**
     * Answers the problem the command reports for one attribute, or null when it accepts the attribute.
     */
    public function problem(Attribute $attribute, bool $type, GrammarRelease $release): ?AttributeProblem
    {
        $known = $attribute->known;
        $name = $attribute->name->value;
        if ($known === null) {
            return new AttributeProblem($type ? AttributeProblemKind::UnrecognizedChangedTypeAttribute : AttributeProblemKind::UnrecognizedChangedOperatorAttribute, [$name]);
        }
        if (!$this->changeable($known, $release)) {
            return new AttributeProblem($type ? AttributeProblemKind::UnchangeableTypeAttribute : AttributeProblemKind::UnchangeableOperatorAttribute, [$name]);
        }
        $value = $attribute->value;
        if ($value instanceof AttributeArgument || ($value === null && $known->reading() === Reading::Function)) {
            return null;
        }

        return (new AttributeChecks())->rejected($known->reading(), $name, $value);
    }

    /**
     * Tells whether the command changes a recognized attribute.
     */
    public function changeable(KnownAttribute $known, GrammarRelease $release): bool
    {
        $added = [OperatorChangeAttribute::Commutator, OperatorChangeAttribute::Negator, OperatorChangeAttribute::Merges, OperatorChangeAttribute::Hashes];

        return $known->reading() !== Reading::Ignored && ($release !== GrammarRelease::PostgreSql166 || !in_array($known, $added, true));
    }
}
