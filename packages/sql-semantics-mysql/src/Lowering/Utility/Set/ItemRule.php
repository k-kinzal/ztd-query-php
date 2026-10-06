<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Set;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NamesExpression;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers one item of a SET list.
 *
 * Rule: MYSQL-SET-ITEM-LOWERING-001. Scope: option_value_no_option_type,
 * option_value_following_option_type, internal_variable_name (5.6, 5.7),
 * and the variable names of lvalue_variable (8.0 and later, also lowered by
 * the leaf rules). Constructs: NameAssignment, SystemAssignment,
 * UserAssignment, SetNames, SetCharacterSet, NamesExpression and, in MySQL
 * 5.6, PasswordAssignment. The values are lowered by
 * MYSQL-SET-VALUE-LOWERING-001. `DEFAULT.x` names the instance `default`
 * (UtilityNoise). Terminates: every part is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set-names.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set-character-set.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Utility
 */
final class ItemRule
{
    /**
     * The MySQL 5.6 password items, by the position of the account (null for none) and of the password.
     */
    private const PASSWORDS = [
        'option_value_no_option_type: PASSWORD equal text_or_password' => [null, 2],
        'option_value_no_option_type: PASSWORD FOR_SYM user equal text_or_password' => [2, 4],
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Tells whether an item is a MySQL 5.6 password item.
     */
    public function password(Form $form): bool
    {
        return isset(self::PASSWORDS[$form->signature]);
    }

    /**
     * Lowers an item written without a leading scope keyword: a form of `option_value_no_option_type`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function unscoped(Form $form): SetItem
    {
        $values = new ValueRule($this->lowering);
        switch ($form->signature) {
            case 'option_value_no_option_type: internal_variable_name equal set_expr_or_default':
            case 'option_value_no_option_type: lvalue_variable equal set_expr_or_default':
                return $this->named(null, $form);
            case 'option_value_no_option_type: @ ident_or_text equal expr':
                return new UserAssignment($this->lowering->variables->user($form->node(1)), $this->lowering->expressions->expression($form->node(3)));
            case 'option_value_no_option_type: @ @ opt_var_ident_type internal_variable_name equal set_expr_or_default':
                $this->lowering->options->skip($form->node(4));
                [$name, $instance] = $this->variable($form->node(3));

                return new SystemAssignment($this->lowering->leaves->record(new SystemVariable($name, $this->lowering->variables->scope($form->node(2)), $instance)), $values->value($form->node(5), true));
            case 'option_value_no_option_type: @ @ opt_set_var_ident_type lvalue_variable equal set_expr_or_default':
                $this->lowering->options->skip($form->node(4));

                return new SystemAssignment($this->lowering->variables->system($form->node(3), $this->lowering->variables->scope($form->node(2))), $values->value($form->node(5), true));
            default:
                return $this->connection($form);
        }
    }

    /**
     * Lowers an item that sets the connection character sets, or a MySQL 5.6 password item.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function connection(Form $form): SetItem
    {
        $charsets = $this->lowering->charsets;
        switch ($form->signature) {
            case 'option_value_no_option_type: charset old_or_new_charset_name_or_default':
            case 'option_value_no_option_type: character_set old_or_new_charset_name_or_default':
                $this->lowering->options->skip($form->node(0));

                return new SetCharacterSet($charsets->charset($form->node(1)));
            case 'option_value_no_option_type: NAMES_SYM charset_name_or_default opt_collate':
            case 'option_value_no_option_type: NAMES_SYM charset_name opt_collate':
                return new SetNames($charsets->charset($form->node(1)), $charsets->collation($form->node(2)));
            case 'option_value_no_option_type: NAMES_SYM DEFAULT_SYM':
                return new SetNames(new CharsetName(null));
            case 'option_value_no_option_type: NAMES_SYM equal expr':
                $this->lowering->options->skip($form->node(1));

                return new NamesExpression($this->lowering->expressions->expression($form->node(2)));
        }
        [$user, $password] = self::PASSWORDS[$form->signature] ?? throw ImplementationGap::production($form);
        $this->lowering->options->skip($form->node($password - 1));

        return new PasswordAssignment($this->lowering->accounts->password($form->node($password)), $user === null ? null : $this->lowering->users->account($form->node($user)));
    }

    /**
     * Lowers an item written after a scope keyword: a form of `option_value_following_option_type`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function scoped(VariableScope $scope, Form $form): NameAssignment
    {
        if ($form->signature !== 'option_value_following_option_type: internal_variable_name equal set_expr_or_default'
            && $form->signature !== 'option_value_following_option_type: lvalue_variable equal set_expr_or_default') {
            throw ImplementationGap::production($form);
        }

        return $this->named($scope, $form);
    }

    /**
     * Lowers a name assignment whose name, equals sign and value are the first three children.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function named(?VariableScope $scope, Form $form): NameAssignment
    {
        [$name, $qualifier] = $this->variable($form->node(0));
        $this->lowering->options->skip($form->node(1));

        return new NameAssignment($name, (new ValueRule($this->lowering))->value($form->node(2), $scope !== null), $scope, $qualifier);
    }

    /**
     * Lowers a variable name and the qualifier written before it: a node of `internal_variable_name` or `lvalue_variable`.
     *
     * @return array{Name, Name|null}
     * @throws ImplementationGap When a production has no rule
     */
    public function variable(Node $variable): array
    {
        $form = $this->lowering->form($variable);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'internal_variable_name: ident', 'lvalue_variable: lvalue_ident' => [$names->identifier($form->node(0)), null],
            'internal_variable_name: ident . ident', 'lvalue_variable: lvalue_ident . ident' => [$names->identifier($form->node(2)), $names->identifier($form->node(0))],
            'internal_variable_name: DEFAULT . ident', 'lvalue_variable: DEFAULT_SYM . ident' => [$names->identifier($form->node(2)), $this->lowering->leaves->record(new Name('default'))],
            default => throw ImplementationGap::production($form),
        };
    }
}
