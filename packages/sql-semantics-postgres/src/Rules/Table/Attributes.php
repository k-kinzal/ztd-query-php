<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Statement\Identifier\Name;
use UnitEnum;

/**
 * Checks the attributes written after a table constraint.
 *
 * Rule: PG-CONSTRAINT-ATTRIBUTES-001. The attributes are kept in the order
 * written. The server rejects, as it reads `ConstraintAttributeSpec`, NOT
 * DEFERRABLE together with INITIALLY DEFERRED ("constraint declared
 * INITIALLY DEFERRED must be DEFERRABLE"), and DEFERRABLE with NOT
 * DEFERRABLE or INITIALLY DEFERRED with INITIALLY IMMEDIATE ("conflicting
 * constraint properties"). Then each kind of constraint admits only some of
 * them (`processCASbits`): DEFERRABLE and INITIALLY DEFERRED only UNIQUE,
 * PRIMARY KEY, EXCLUDE and FOREIGN KEY; NOT VALID only CHECK and FOREIGN KEY;
 * NO INHERIT only CHECK. NOT DEFERRABLE and INITIALLY IMMEDIATE are the
 * defaults and always admitted. Source:
 * https://www.postgresql.org/docs/17/sql-createtable.html. Termination: one
 * pass over the attributes. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Attributes
{
    /**
     * Checks that a list holds attributes.
     *
     * @param array<array-key, object|scalar|null> $attributes
     * @return list<ConstraintAttribute>
     */
    public function checked(array $attributes): array
    {
        $checked = [];
        foreach (Check::listOf($attributes, UnitEnum::class, 'Constraint attributes are an ordered list.') as $attribute) {
            Check::input($attribute instanceof ConstraintAttribute, 'Constraint attributes are attribute values.');
            $checked[] = $attribute;
        }

        return $checked;
    }

    /**
     * Reports the conflicts and the attributes the kind of constraint does not admit.
     *
     * @param list<ConstraintAttribute> $attributes
     * @param string $label The name of the kind of constraint in the server's messages
     */
    public function report(Derivation $derivation, array $attributes, string $label, bool $deferrable, bool $notValid, bool $noInherit): void
    {
        $written = static fn (ConstraintAttribute $attribute): bool => in_array($attribute, $attributes, true);
        if ($written(ConstraintAttribute::NotDeferrable) && $written(ConstraintAttribute::InitiallyDeferred)) {
            $derivation->report(new DefinitionProblem(DefinitionRule::DeferredNotDeferrable));
        } elseif (($written(ConstraintAttribute::NotDeferrable) && $written(ConstraintAttribute::Deferrable))
            || ($written(ConstraintAttribute::InitiallyDeferred) && $written(ConstraintAttribute::InitiallyImmediate))) {
            $derivation->report(new DefinitionProblem(DefinitionRule::ConflictingAttributes));
        }
        $subject = new Name($label);
        if (!$deferrable && ($written(ConstraintAttribute::Deferrable) || $written(ConstraintAttribute::InitiallyDeferred))) {
            $derivation->report(new DefinitionProblem(DefinitionRule::CannotDefer, $subject));
        }
        if (!$notValid && $written(ConstraintAttribute::NotValid)) {
            $derivation->report(new DefinitionProblem(DefinitionRule::CannotNotValid, $subject));
        }
        if (!$noInherit && $written(ConstraintAttribute::NoInherit)) {
            $derivation->report(new DefinitionProblem(DefinitionRule::CannotNoInherit, $subject));
        }
    }
}
