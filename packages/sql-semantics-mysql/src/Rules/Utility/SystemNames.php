<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Shared name rules for a system-variable read and an assignment target.
 *
 * Rule: MYSQL-SYSTEM-NAMES-001. MySQL 5.x resolves the name after an
 * instance independently; later releases resolve the whole component name.
 * Key-cache parameters are structured in every release. Assignment and
 * read occurrences keep distinct diagnostics. Rendering writes the same
 * qualified name for both roles.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/structured-system-variables.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SystemNames
{
    /**
     * Checks a structured name without treating an assignment as a value read.
     */
    public function structured(Name $name, ?Name $instance, bool $assigned, Derivation $derivation): void
    {
        if ($instance === null || in_array(strtolower($name->value), SystemVariable::KEY_CACHE, true)) {
            return;
        }
        $grammar = $derivation->context->profile->grammar;
        $variables = SystemVariables::of($grammar);
        if ($grammar === GrammarRelease::MySql5651 || $grammar === GrammarRelease::MySql5744) {
            $derivation->report($variables->find($name->value) === null ? new UnknownSystemVariable($name->value, $assigned, true) : new UnstructuredVariable($name->value));

            return;
        }
        $qualified = $instance->value . '.' . $name->value;
        if ($variables->find($qualified) === null) {
            $derivation->report(new UnknownSystemVariable($qualified, $assigned, true));
        }
    }

    /**
     * Writes both at signs and the qualified variable name without spaces.
     */
    public function render(Name $name, ?VariableScope $scope, ?Name $instance, Output $out): void
    {
        $out->symbol('@')->glue()->symbol('@')->glue();
        if ($scope !== null) {
            $out->keyword($scope->value)->symbol('.');
        }
        if ($instance !== null) {
            if ($instance->quote === \SqlSemantics\Statement\Identifier\NameQuote::Keyword && strcasecmp($instance->value, 'default') === 0) {
                $out->keyword('DEFAULT')->separate();
            } else {
                $out->name($instance, NameUse::Label);
            }
            $out->symbol('.');
        }
        $out->name($name, NameUse::Label);
    }
}
