<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Utility\SystemNames;
use SqlSemantics\Platform\MySql\Rules\Utility\VariableAccess;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A system variable read as a value: `@@name`, `@@GLOBAL.name`, `@@instance.name`.
 *
 * A structured variable is written with the name of its instance or
 * component before the variable name; `DEFAULT.name` names the instance
 * `default`. Without a scope the session value is read when the variable has
 * one and the global value otherwise.
 *
 * Rule: MYSQL-SYSTEM-VARIABLE-001. Facts: the type and the NULL fact are
 * those of the variable in the running server, which no context holds; the
 * fact names the variable as missing session state; a parameter of a named
 * key cache has the type of the variable of the default key cache, as every
 * parameter is an integer. Diagnostics: a variable
 * read with an instance name that is neither a key cache variable nor a
 * variable the release knows by that full name is unknown, named with its
 * instance (verified on a live 8.4 server); MySQL 5.6 and 5.7 read the name
 * after the instance as the variable, unknown by that name or, when they
 * know it, not structured (verified on live 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/using-system-variables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/structured-system-variables.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a scoped system variable
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a = @@GLOBAL.sort_buffer_size');
 *     [$query->statement->query->where->right->scope, $query->statement->query->where->right->name->value] // => [\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Global, 'sort_buffer_size']
 */
final class SystemVariable implements Scalar
{
    use Snapshot;

    /**
     * The variables of a key cache, the only structured variables a server without components has.
     */
    public const KEY_CACHE = ['key_buffer_size', 'key_cache_block_size', 'key_cache_division_limit', 'key_cache_age_threshold'];

    /**
     * @param Name $name The variable name
     * @param VariableScope|null $scope The read scope: GLOBAL, SESSION or the implicit scope
     * @param Name|null $instance The instance or component name of a structured variable
     */
    public function __construct(public readonly Name $name, public readonly ?VariableScope $scope = null, public readonly ?Name $instance = null)
    {
        Check::input(in_array($scope, [null, VariableScope::Global, VariableScope::Session], true), 'A system-variable read has an implicit, GLOBAL or SESSION scope.');
    }

    /**
     * Derives the type of a read from the variables of the release, reporting a variable the read cannot have.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        if ($this->instance === null) {
            Deprecation::variable($this->name->value, $derivation);
        }
        $subject = 'system variable @@' . ($this->scope === null ? '' : $this->scope->value . '.') . ($this->instance === null ? '' : $this->instance->value . '.') . $this->name->value;
        $dependent = new ScalarFact(new Dependent([new SessionState($subject)]), Nullability::Dependent);
        $this->structured($derivation);
        if ($this->instance !== null && !in_array(strtolower($this->name->value), self::KEY_CACHE, true)) {
            return $dependent;
        }
        $definition = $this->instance === null ? (new VariableAccess())->read($this->name->value, $this->scope, $derivation) : SystemVariables::of($derivation->context->profile->grammar)->find($this->name->value);

        return $definition === null ? $dependent : new ScalarFact(new Known($definition->domain), Nullability::Nullable);
    }

    /**
     * Reports a variable written with an instance name that the release does not have by that name: unknown, named with its instance; in MySQL 5.6 and 5.7, which read the name after the instance as the variable, unknown by that name, or not structured when they know it (verified on live 5.6.51, 5.7.44 and 8.4 servers).
     */
    public function structured(Derivation $derivation): void
    {
        (new SystemNames())->structured($this->name, $this->instance, false, $derivation);
    }

    /**
     * Writes both at signs, the scope, the instance and the name without spaces.
     */
    public function render(Output $out): void
    {
        (new SystemNames())->render($this->name, $this->scope, $this->instance, $out);
    }
}
