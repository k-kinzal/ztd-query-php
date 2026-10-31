<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers user-defined variables, system variables and their scopes.
 *
 * Rule: MYSQL-VARIABLE-001. Scope: variable, variable_aux,
 * opt_var_ident_type (5.6, 5.7); rvalue_system_or_user_variable,
 * rvalue_system_variable, opt_rvalue_system_variable_type,
 * in_expression_user_variable_assignment, lvalue_variable,
 * opt_set_var_ident_type (8.0 and later). `@name` is a user variable,
 * `@name := expr` an assignment, `@@[scope.][instance.]name` a system
 * variable. LOCAL retains its spelling and denotes SESSION; `DEFAULT.name` names the instance
 * `default`. Constructs: UserVariable, VariableAssignment, SystemVariable,
 * VariableScope. Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/user-variables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/using-system-variables.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class VariableRule
{
    /**
     * The scope productions, by the scope they select.
     */
    private const SCOPES = [
        'opt_var_ident_type: GLOBAL_SYM .' => VariableScope::Global, 'opt_var_ident_type: LOCAL_SYM .' => VariableScope::Local,
        'opt_var_ident_type: SESSION_SYM .' => VariableScope::Session, 'opt_rvalue_system_variable_type: GLOBAL_SYM .' => VariableScope::Global,
        'opt_rvalue_system_variable_type: LOCAL_SYM .' => VariableScope::Local, 'opt_rvalue_system_variable_type: SESSION_SYM .' => VariableScope::Session,
        'opt_set_var_ident_type: PERSIST_SYM .' => VariableScope::Persist, 'opt_set_var_ident_type: PERSIST_ONLY_SYM .' => VariableScope::PersistOnly,
        'opt_set_var_ident_type: GLOBAL_SYM .' => VariableScope::Global, 'opt_set_var_ident_type: LOCAL_SYM .' => VariableScope::Local,
        'opt_set_var_ident_type: SESSION_SYM .' => VariableScope::Session,
    ];

    /**
     * The scope productions that write no scope.
     */
    private const UNSCOPED = ['opt_var_ident_type:' => true, 'opt_rvalue_system_variable_type:' => true, 'opt_set_var_ident_type:' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a variable read, or a user variable assignment, at an expression position.
     *
     * @throws ImplementationGap When the production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When the system variable is unknown to the release
     */
    public function variable(Node $variable): UserVariable|SystemVariable|VariableAssignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignment
    {
        $form = $this->lowering->productions->form($variable);
        if ($form->signature === 'variable: @ variable_aux') {
            $form = $this->lowering->productions->form($form->node(1));
        }

        return match ($form->signature) {
            'variable_aux: ident_or_text', 'rvalue_system_or_user_variable: @ ident_or_text' => $this->user($form->node(count($form->node->children) - 1)),
            'variable_aux: ident_or_text SET_VAR expr' => $this->assignment($form->node(0), $form->node(2)),
            'in_expression_user_variable_assignment: @ ident_or_text SET_VAR expr' => $this->assignment($form->node(1), $form->node(3)),
            'variable_aux: @ opt_var_ident_type ident_or_text opt_component' => $this->legacySystem($form),
            'rvalue_system_or_user_variable: @ @ opt_rvalue_system_variable_type rvalue_system_variable' => $this->system($form->node(3), $this->scope($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a user variable name.
     */
    public function user(Node $name): UserVariable
    {
        return $this->lowering->leaves->record(new UserVariable($this->lowering->names->identifier($name, \SqlSemantics\Contract\NameUse::Variable)));
    }

    /**
     * Lowers a user variable assignment.
     */
    public function assignment(Node $name, Node $value): VariableAssignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignment
    {
        $target = $this->user($name);
        $operand = $this->lowering->expressions->operand($value);
        return $operand instanceof \SqlSemantics\Statement\Scalar
            ? $this->lowering->leaves->record(new VariableAssignment($target, $operand))
            : new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignment($target, $operand);
    }

    /**
     * Lowers the system variable form of MySQL 5.6 and 5.7: a name and an optional component after it.
     * @throws \SqlSemantics\Construction\RejectedInput When the system variable is unknown to the release
     */
    public function legacySystem(Form $form): SystemVariable
    {
        $first = $this->lowering->names->identifier($form->node(2));
        $second = $this->lowering->names->optional($form->node(3));
        $scope = $this->scope($form->node(1));
        $unknown = $this->unknown($second ?? $first, $second === null ? null : $first, false) ?? $this->scoped($second ?? $first, $second === null ? null : $first, $scope, false);
        if ($unknown !== null) {
            throw new \SqlSemantics\Construction\RejectedInput($unknown, (new \SqlSemantics\Construction\SourceRanges())->span($form->node)[1] ?? 0, false);
        }

        return $this->lowering->leaves->record($second === null ? new SystemVariable($first, $scope) : new SystemVariable($second, $scope, $first));
    }

    /**
     * Lowers a system variable name of MySQL 8.0 and later, read or assigned, under a scope.
     *
     * @param bool $component Whether the name belongs to a component being installed, whose variables exist only once it is loaded
     * @return ($assigned is true ? SystemTarget : SystemVariable)
     * @throws ImplementationGap When the production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When the system variable is unknown to the release
     */
    public function system(Node $name, ?VariableScope $scope, bool $assigned = false, bool $component = false): SystemVariable|SystemTarget
    {
        $form = $this->lowering->productions->form($name);
        $names = $this->lowering->names;

        [$variable, $instance] = match ($form->signature) {
            'rvalue_system_variable: ident_or_text', 'lvalue_variable: lvalue_ident' => [$names->identifier($form->node(0)), null],
            'rvalue_system_variable: ident_or_text . ident', 'lvalue_variable: lvalue_ident . ident' => [$names->identifier($form->node(2)), $names->identifier($form->node(0))],
            'lvalue_variable: DEFAULT_SYM . ident' => [$names->identifier($form->node(2)), $this->lowering->leaves->record(new Name('default', \SqlSemantics\Statement\Identifier\NameQuote::Keyword))],
            default => throw ImplementationGap::production($form),
        };
        $unknown = $component ? null : ($this->unknown($variable, $instance, $assigned) ?? $this->scoped($variable, $instance, $scope, $assigned));
        if ($unknown !== null) {
            throw new \SqlSemantics\Construction\RejectedInput($unknown, (new \SqlSemantics\Construction\SourceRanges())->span($name)[1] ?? 0, false);
        }

        return $this->lowering->origins->record($this->lowering->leaves->record($assigned ? new SystemTarget($variable, $scope, $instance) : new SystemVariable($variable, $scope, $instance)), $name);
    }

    /**
     * Answers the scope misuse of a known variable read or assigned under a scope the catalog does not give
     * it, or null. MySQL 8.0 and later report a read under the wrong scope once the statement is read, even inside
     * a stored program body; 5.6 and 5.7 report it when the statement runs, among the other conditions of that run,
     * so a read is left to the run there. Every release checks an assignment before any assignment of the statement
     * takes effect, so a standalone assignment is refused once read while a stored body keeps it. A standalone
     * assignment of a read-only variable, or of a global-only variable under a session scope, is refused the same
     * way (verified on 5.7.44 and 8.4.7).
     */
    public function scoped(Name $variable, ?Name $instance, ?VariableScope $scope, bool $assigned): ?\SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse
    {
        $grammar = $this->lowering->profile->grammar;
        $legacy = in_array($grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        if ($instance !== null || ($legacy && !$assigned) || ($this->lowering->stored() && $assigned)) {
            return null;
        }
        $definition = \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables::of($grammar)->find($variable->value);
        if ($definition === null) {
            return null;
        }
        $rules = \SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableRule::class;
        if ($scope === null) {
            return $assigned && $definition->writability === \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability::ReadOnly ? new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse($rules::ReadOnly, $variable->value) : null;
        }
        $session = $scope === VariableScope::Session || $scope === VariableScope::Local;
        if (!$session && !$definition->reach->global()) {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse($assigned ? $rules::SetGlobalOfSessionVariable : $rules::GlobalOfSessionVariable, $variable->value);
        }
        if ($session && !$definition->reach->session()) {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse($assigned ? $rules::SetSessionOfGlobalVariable : $rules::SessionOfGlobalVariable, $variable->value);
        }
        if ($assigned && $definition->writability === \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability::ReadOnly) {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse($rules::ReadOnly, $variable->value);
        }
        if ($assigned && $session && $definition->writability === \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability::GlobalOnly) {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse($rules::SessionReadOnly, $variable->value);
        }

        return null;
    }

    /**
     * Lowers the optional scope written before a system variable name.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function scope(Node $scope): ?VariableScope
    {
        $form = $this->lowering->productions->form($scope);
        if (isset(self::UNSCOPED[$form->signature])) {
            return null;
        }

        return self::SCOPES[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Answers the problem of a system variable name the server refuses while reading (verified on 5.6.51, 5.7.44 and 8.4.7):
     * a plain name the release does not know (ER_UNKNOWN_SYSTEM_VARIABLE), and in 5.6 and 5.7 a structured name that is no
     * key cache name: it names a variable that takes no component (1272) or an unknown variable. From MySQL 8.0 a structured
     * name may be a component variable, which an installed component supplies: database state, resolved when derived.
     */
    public function unknown(Name $variable, ?Name $instance, bool $assigned): \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable|\SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable|null
    {
        $grammar = $this->lowering->profile->grammar;
        $variables = \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables::of($grammar);
        if ($instance === null) {
            return $variables->find($variable->value) === null ? new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable($variable->value, $assigned) : null;
        }
        if (in_array(strtolower($variable->value), SystemVariable::KEY_CACHE, true)) {
            return null;
        }
        if ($grammar === \SqlSemantics\Contract\GrammarRelease::MySql5651 || $grammar === \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            return $variables->find($variable->value) === null
                ? new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable($variable->value, $assigned, true)
                : new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable($variable->value);
        }

        return null;
    }
}
