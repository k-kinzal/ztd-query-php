<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers user-defined variables, system variables and their scopes.
 *
 * Rule: MYSQL-VARIABLE-001. Scope: variable, variable_aux,
 * opt_var_ident_type (5.6, 5.7); rvalue_system_or_user_variable,
 * rvalue_system_variable, opt_rvalue_system_variable_type,
 * in_expression_user_variable_assignment, lvalue_variable,
 * opt_set_var_ident_type (8.0 and later). `@name` is a user variable,
 * `@name := expr` an assignment, `@@[scope.][instance.]name` a system
 * variable. LOCAL is the scope SESSION; `DEFAULT.name` names the instance
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
        'opt_var_ident_type: GLOBAL_SYM .' => VariableScope::Global, 'opt_var_ident_type: LOCAL_SYM .' => VariableScope::Session,
        'opt_var_ident_type: SESSION_SYM .' => VariableScope::Session, 'opt_rvalue_system_variable_type: GLOBAL_SYM .' => VariableScope::Global,
        'opt_rvalue_system_variable_type: LOCAL_SYM .' => VariableScope::Session, 'opt_rvalue_system_variable_type: SESSION_SYM .' => VariableScope::Session,
        'opt_set_var_ident_type: PERSIST_SYM .' => VariableScope::Persist, 'opt_set_var_ident_type: PERSIST_ONLY_SYM .' => VariableScope::PersistOnly,
        'opt_set_var_ident_type: GLOBAL_SYM .' => VariableScope::Global, 'opt_set_var_ident_type: LOCAL_SYM .' => VariableScope::Session,
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
     */
    public function variable(Node $variable): Scalar
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
        return $this->lowering->leaves->record(new UserVariable($this->lowering->names->identifier($name)));
    }

    /**
     * Lowers a user variable assignment.
     */
    public function assignment(Node $name, Node $value): VariableAssignment
    {
        return $this->lowering->leaves->record(new VariableAssignment($this->user($name), $this->lowering->expressions->expression($value)));
    }

    /**
     * Lowers the system variable form of MySQL 5.6 and 5.7: a name and an optional component after it.
     */
    public function legacySystem(Form $form): SystemVariable
    {
        $first = $this->lowering->names->identifier($form->node(2));
        $second = $this->lowering->names->optional($form->node(3));
        $scope = $this->scope($form->node(1));

        return $this->lowering->leaves->record($second === null ? new SystemVariable($first, $scope) : new SystemVariable($second, $scope, $first));
    }

    /**
     * Lowers a system variable name of MySQL 8.0 and later, read or assigned, under a scope.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function system(Node $name, ?VariableScope $scope): SystemVariable
    {
        $form = $this->lowering->productions->form($name);
        $names = $this->lowering->names;

        return $this->lowering->leaves->record(match ($form->signature) {
            'rvalue_system_variable: ident_or_text', 'lvalue_variable: lvalue_ident' => new SystemVariable($names->identifier($form->node(0)), $scope),
            'rvalue_system_variable: ident_or_text . ident', 'lvalue_variable: lvalue_ident . ident' => new SystemVariable($names->identifier($form->node(2)), $scope, $names->identifier($form->node(0))),
            'lvalue_variable: DEFAULT_SYM . ident' => new SystemVariable($names->identifier($form->node(2)), $scope, $this->lowering->leaves->record(new Name('default'))),
            default => throw ImplementationGap::production($form),
        });
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
}
