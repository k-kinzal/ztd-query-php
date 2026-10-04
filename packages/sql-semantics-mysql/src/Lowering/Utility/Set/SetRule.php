<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Set;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\AccessMode;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\IsolationLevel;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetTransaction;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Statement;

/**
 * Lowers SET statements: the assignment list, SET TRANSACTION, and the routing of SET PASSWORD.
 *
 * Rule: MYSQL-SET-LOWERING-001. Scope: set, start_option_value_list,
 * start_option_value_list_following_option_type,
 * option_value_list_continued, option_value_list, option_value,
 * option_type, transaction_characteristics, transaction_access_mode,
 * isolation_level, transaction_access_mode_types, isolation_types,
 * opt_transaction_access_mode, opt_isolation_level. Constructs:
 * SetVariables, SetTransaction. The PASSWORD forms of
 * start_option_value_list, and a MySQL 5.6 list whose only item is a
 * password, are the account statement SET PASSWORD and go to the account
 * family. The items are lowered by MYSQL-SET-ITEM-LOWERING-001. LOCAL is the
 * scope SESSION (UtilityNoise). Terminates: the list spine is flattened
 * iteratively and every item is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set-transaction.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Utility
 */
final class SetRule
{
    /**
     * The scope keywords before a variable name or TRANSACTION.
     */
    private const SCOPES = [
        'option_type: GLOBAL_SYM' => VariableScope::Global, 'option_type: LOCAL_SYM' => VariableScope::Session,
        'option_type: SESSION_SYM' => VariableScope::Session, 'option_type: PERSIST_SYM' => VariableScope::Persist,
        'option_type: PERSIST_ONLY_SYM' => VariableScope::PersistOnly,
    ];

    /**
     * The PASSWORD forms of the statement, which are SET PASSWORD of the account family.
     */
    private const PASSWORDS = [
        'start_option_value_list: PASSWORD equal password' => true, 'start_option_value_list: PASSWORD equal PASSWORD ( password )' => true,
        'start_option_value_list: PASSWORD FOR_SYM user equal password' => true, 'start_option_value_list: PASSWORD FOR_SYM user equal PASSWORD ( password )' => true,
        'start_option_value_list: PASSWORD equal TEXT_STRING_password opt_replace_password opt_retain_current_password' => true,
        'start_option_value_list: PASSWORD TO_SYM RANDOM_SYM opt_replace_password opt_retain_current_password' => true,
        'start_option_value_list: PASSWORD FOR_SYM user equal TEXT_STRING_password opt_replace_password opt_retain_current_password' => true,
        'start_option_value_list: PASSWORD FOR_SYM user TO_SYM RANDOM_SYM opt_replace_password opt_retain_current_password' => true,
    ];

    /**
     * The isolation levels by production.
     */
    private const LEVELS = [
        'isolation_types: READ_SYM UNCOMMITTED_SYM' => IsolationLevel::ReadUncommitted, 'isolation_types: READ_SYM COMMITTED_SYM' => IsolationLevel::ReadCommitted,
        'isolation_types: REPEATABLE_SYM READ_SYM' => IsolationLevel::RepeatableRead, 'isolation_types: SERIALIZABLE_SYM' => IsolationLevel::Serializable,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of `set`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        if ($form->signature !== 'set: SET start_option_value_list' && $form->signature !== 'set: SET_SYM start_option_value_list') {
            throw ImplementationGap::production($form);
        }
        $list = $this->lowering->form($form->node(1));
        if (isset(self::PASSWORDS[$list->signature])) {
            return $this->lowering->accounts->setPassword($list);
        }

        return match ($list->signature) {
            'start_option_value_list: option_value_no_option_type option_value_list_continued' => $this->unscoped($list),
            'start_option_value_list: TRANSACTION_SYM transaction_characteristics' => new SetTransaction($this->characteristics($list->node(1))),
            'start_option_value_list: option_type start_option_value_list_following_option_type' => $this->scoped($this->scope($list->node(0)), $this->lowering->form($list->node(1))),
            default => throw ImplementationGap::production($list),
        };
    }

    /**
     * Lowers a list that starts without a scope keyword; a MySQL 5.6 list of one password is SET PASSWORD.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function unscoped(Form $list): Statement
    {
        $items = new ItemRule($this->lowering);
        $first = $this->lowering->form($list->node(0));
        $rest = $this->lowering->form($list->node(1));
        if ($rest->signature === 'option_value_list_continued:' && $items->password($first)) {
            return $this->lowering->accounts->setPassword($first);
        }

        return new SetVariables([$items->unscoped($first), ...$this->continued($rest)]);
    }

    /**
     * Lowers the rest of a statement after a leading scope keyword.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function scoped(VariableScope $scope, Form $form): Statement
    {
        return match ($form->signature) {
            'start_option_value_list_following_option_type: option_value_following_option_type option_value_list_continued' => new SetVariables([
                (new ItemRule($this->lowering))->scoped($scope, $this->lowering->form($form->node(0))),
                ...$this->continued($this->lowering->form($form->node(1))),
            ]),
            'start_option_value_list_following_option_type: TRANSACTION_SYM transaction_characteristics' => new SetTransaction($this->characteristics($form->node(1)), $scope),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the items after the first: a node of `option_value_list_continued`.
     *
     * @return list<SetItem>
     * @throws ImplementationGap When a production has no rule
     */
    public function continued(Form $form): array
    {
        if ($form->signature === 'option_value_list_continued:') {
            return [];
        }
        if ($form->signature !== 'option_value_list_continued: , option_value_list') {
            throw ImplementationGap::production($form);
        }
        $items = new ItemRule($this->lowering);
        $lowered = [];
        for ($spine = $form->node(1); $spine instanceof Node && $spine->name === 'option_value_list'; $spine = $spine->children[0]) {
            $link = $this->lowering->form($spine);
            if ($link->signature !== 'option_value_list: option_value' && $link->signature !== 'option_value_list: option_value_list , option_value') {
                throw ImplementationGap::production($link);
            }
        }
        foreach ((new Lists())->items($form->node(1)) as $value) {
            $option = $this->lowering->form($value);
            $lowered[] = match ($option->signature) {
                'option_value: option_type option_value_following_option_type' => $items->scoped($this->scope($option->node(0)), $this->lowering->form($option->node(1))),
                'option_value: option_value_no_option_type' => $items->unscoped($this->lowering->form($option->node(0))),
                default => throw ImplementationGap::production($option),
            };
        }

        return $lowered;
    }

    /**
     * Lowers a scope keyword: a node of `option_type`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function scope(Node $type): VariableScope
    {
        $form = $this->lowering->form($type);

        return self::SCOPES[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers the characteristics of SET TRANSACTION in the order written: a node of `transaction_characteristics`.
     *
     * @return list<IsolationLevel|AccessMode>
     * @throws ImplementationGap When a production has no rule
     */
    public function characteristics(Node $characteristics): array
    {
        $form = $this->lowering->form($characteristics);

        return match ($form->signature) {
            'transaction_characteristics: transaction_access_mode', 'transaction_characteristics: isolation_level' => [$this->characteristic($form->node(0))],
            'transaction_characteristics: transaction_access_mode , isolation_level', 'transaction_characteristics: isolation_level , transaction_access_mode' => [$this->characteristic($form->node(0)), $this->characteristic($form->node(2))],
            'transaction_characteristics: transaction_access_mode opt_isolation_level', 'transaction_characteristics: isolation_level opt_transaction_access_mode' => [$this->characteristic($form->node(0)), ...$this->optional($form->node(1))],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the optional second characteristic: a node of `opt_isolation_level` or `opt_transaction_access_mode`.
     *
     * @return list<IsolationLevel|AccessMode>
     * @throws ImplementationGap When a production has no rule
     */
    public function optional(Node $optional): array
    {
        $form = $this->lowering->form($optional);

        return match ($form->signature) {
            'opt_isolation_level:', 'opt_transaction_access_mode:' => [],
            'opt_isolation_level: , isolation_level', 'opt_transaction_access_mode: , transaction_access_mode' => [$this->characteristic($form->node(1))],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers one characteristic: a node of `isolation_level` or `transaction_access_mode`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function characteristic(Node $characteristic): IsolationLevel|AccessMode
    {
        $form = $this->lowering->form($characteristic);
        if ($form->signature === 'isolation_level: ISOLATION LEVEL_SYM isolation_types') {
            $level = $this->lowering->form($form->node(2));

            return self::LEVELS[$level->signature] ?? throw ImplementationGap::production($level);
        }
        if ($form->signature !== 'transaction_access_mode: transaction_access_mode_types') {
            throw ImplementationGap::production($form);
        }
        $mode = $this->lowering->form($form->node(0));

        return match ($mode->signature) {
            'transaction_access_mode_types: READ_SYM ONLY_SYM' => AccessMode::ReadOnly,
            'transaction_access_mode_types: READ_SYM WRITE_SYM' => AccessMode::ReadWrite,
            default => throw ImplementationGap::production($mode),
        };
    }
}
