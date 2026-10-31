<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Set;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers one item of a SET list.
 *
 * Rule: MYSQL-SET-ITEM-LOWERING-001. Scope: option_value_no_option_type,
 * option_value_following_option_type, internal_variable_name (5.6, 5.7),
 * and the variable names of lvalue_variable (8.0 and later, also lowered by
 * the leaf rules). Constructs: NameAssignment, SystemAssignment,
 * UserAssignment, SetNames, SetCharacterSet and, in MySQL
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
     * MySQL 8.0 and later resolve the value of a `@@variable` assignment before the assigned variable.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When an operand has no SET-item meaning
     */
    public function unscoped(Form $form): SetItem
    {
        $values = new ValueRule($this->lowering);
        switch ($form->signature) {
            case 'option_value_no_option_type: internal_variable_name equal set_expr_or_default':
            case 'option_value_no_option_type: lvalue_variable equal set_expr_or_default':
                return $this->named(null, $form);
            case 'option_value_no_option_type: @ ident_or_text equal expr':
                return new UserAssignment($this->lowering->variables->user($form->node(1)), $this->lowering->expressions->expression($form->node(3)), operator: $this->lowering->options->assignment($form->node(2)));
            case 'option_value_no_option_type: @ @ opt_var_ident_type internal_variable_name equal set_expr_or_default':
                [$name, $instance] = $this->variable($form->node(3));

                return new SystemAssignment($this->lowering->origins->record($this->lowering->leaves->record(new SystemTarget($name, $this->lowering->variables->scope($form->node(2)), $instance)), $form->node(3)), $values->value($form->node(5), true), operator: $this->lowering->options->assignment($form->node(4)));
            case 'option_value_no_option_type: @ @ opt_set_var_ident_type lvalue_variable equal set_expr_or_default':
                $value = $values->value($form->node(5), true);

                return new SystemAssignment($this->lowering->variables->system($form->node(3), $this->lowering->variables->scope($form->node(2)), true), $value, operator: $this->lowering->options->assignment($form->node(4)));
            default:
                return $this->connection($form);
        }
    }

    /**
     * Lowers an item that sets the connection character sets, or a MySQL 5.6 password item.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When SET NAMES writes a collation of another character set than the named one, which the server refuses while parsing in every release (ER_COLLATION_CHARSET_MISMATCH)
     */
    public function connection(Form $form): SetItem
    {
        $charsets = $this->lowering->charsets;
        switch ($form->signature) {
            case 'option_value_no_option_type: charset old_or_new_charset_name_or_default':
            case 'option_value_no_option_type: character_set old_or_new_charset_name_or_default':
                return new SetCharacterSet($charsets->charset($form->node(1)), form: $this->lowering->types->parts->named($form->node(0)));
            case 'option_value_no_option_type: NAMES_SYM charset_name_or_default opt_collate':
            case 'option_value_no_option_type: NAMES_SYM charset_name opt_collate':
                $charset = $charsets->charset($form->node(1));
                $collation = $charsets->collation($form->node(2));
                $mismatch = (new \SqlSemantics\Platform\MySql\Rules\Utility\SetEncoding())->mismatch($charset, $collation, $this->lowering->profile->grammar);
                if ($mismatch !== null) {
                    throw new \SqlSemantics\Construction\RejectedInput($mismatch, (new \SqlSemantics\Construction\SourceRanges())->span($form->node)[1] ?? 0);
                }

                return new SetNames($charset, $collation);
            case 'option_value_no_option_type: NAMES_SYM DEFAULT_SYM':
                return new SetNames(new CharsetName(null));
            case 'option_value_no_option_type: NAMES_SYM equal expr':
                $range = (new \SqlSemantics\Construction\SourceRanges())->span($form->node(1));
                $source = $range === null ? null : new \SqlSemantics\Statement\Source\Span($range[0], $range[1] - $range[0]);
                return $this->lowering->origins->record(new \SqlSemantics\Platform\MySql\Statement\Utility\Set\NamesAssignment($this->lowering->expressions->operand($form->node(2)), $source, operator: $this->lowering->options->assignment($form->node(1))), $form->node);
        }
        [$user, $password] = self::PASSWORDS[$form->signature] ?? throw ImplementationGap::production($form);

        return new PasswordAssignment($this->lowering->accounts->password($form->node($password)), $user === null ? null : $this->lowering->users->account($form->node($user)), operator: $this->lowering->options->assignment($form->node($password - 1)));
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
     * @throws \SqlSemantics\Construction\RejectedInput When a 5.6 or 5.7 target names an unknown system variable
     */
    public function named(?VariableScope $scope, Form $form): NameAssignment
    {
        [$name, $qualifier] = $this->variable($form->node(0));
        $grammar = $this->lowering->profile->grammar;
        if ($qualifier === null && !$this->lowering->stored() && ($grammar === \SqlSemantics\Contract\GrammarRelease::MySql5651 || $grammar === \SqlSemantics\Contract\GrammarRelease::MySql5744) && \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables::of($grammar)->find($name->value) === null) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable($name->value, true), (new \SqlSemantics\Construction\SourceRanges())->span($form->node(0))[1] ?? 0, false);
        }

        return $this->lowering->origins->record(new NameAssignment($name, (new ValueRule($this->lowering))->value($form->node(2), $scope !== null), $scope, $qualifier, operator: $this->lowering->options->assignment($form->node(1))), $form->node(0));
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
            'internal_variable_name: DEFAULT . ident', 'lvalue_variable: DEFAULT_SYM . ident' => [$names->identifier($form->node(2)), $this->lowering->leaves->record(new Name('default', \SqlSemantics\Statement\Identifier\NameQuote::Keyword))],
            default => throw ImplementationGap::production($form),
        };
    }
}
