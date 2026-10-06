<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Begin;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Commit;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Release;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Rollback;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\RollbackTo;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Savepoint;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\TransactionBehavior;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * Lowers transaction control commands.
 *
 * Rule: SQLITE-TRANSACTION-LOWER-001. Scope: the `cmd` productions of BEGIN,
 * COMMIT, END, ROLLBACK, SAVEPOINT and RELEASE, with transtype, trans_opt and
 * savepoint_opt. Constructors: Begin, Commit, Rollback, RollbackTo, Savepoint,
 * Release. The behavior word and every name are kept; the keywords COMMIT and
 * END, a bare TRANSACTION and the SAVEPOINT before a savepoint name are
 * declared noise. No fact is derived and no diagnostic is emitted. Terminates:
 * every production has a fixed number of children.
 * Source: https://sqlite.org/lang_transaction.html, https://sqlite.org/lang_savepoint.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TransactionRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a transaction control command, or answers null for a command of another family.
     */
    public function command(Form $form): ?Statement
    {
        return match ($form->signature) {
            'cmd: BEGIN transtype trans_opt' => new Begin($this->behavior($form->node(1)), $this->name($form->node(2))),
            'cmd: COMMIT|END trans_opt' => new Commit($this->name($form->node(1))),
            'cmd: ROLLBACK trans_opt' => new Rollback($this->name($form->node(1))),
            'cmd: SAVEPOINT nm' => new Savepoint($this->lowering->names->name($form->node(1))),
            'cmd: RELEASE savepoint_opt nm' => new Release($this->savepoint($form->node(1), $form->node(2))),
            'cmd: ROLLBACK trans_opt TO savepoint_opt nm' => new RollbackTo($this->savepoint($form->node(3), $form->node(4)), $this->name($form->node(1))),
            default => null,
        };
    }

    /**
     * Lowers the optional behavior word of BEGIN.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function behavior(Node $type): ?TransactionBehavior
    {
        $form = $this->lowering->productions->form($type);

        return match ($form->signature) {
            'transtype:' => null,
            'transtype: DEFERRED' => TransactionBehavior::Deferred,
            'transtype: IMMEDIATE' => TransactionBehavior::Immediate,
            'transtype: EXCLUSIVE' => TransactionBehavior::Exclusive,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the optional TRANSACTION clause to the name it carries, if any.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function name(Node $option): ?Name
    {
        $form = $this->lowering->productions->form($option);

        return match ($form->signature) {
            'trans_opt:', 'trans_opt: TRANSACTION' => null,
            'trans_opt: TRANSACTION nm' => $this->lowering->names->name($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a savepoint name written after the optional SAVEPOINT keyword.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function savepoint(Node $keyword, Node $name): Name
    {
        $form = $this->lowering->productions->form($keyword);

        return match ($form->signature) {
            'savepoint_opt:', 'savepoint_opt: SAVEPOINT' => $this->lowering->names->name($name),
            default => throw ImplementationGap::production($form),
        };
    }
}
