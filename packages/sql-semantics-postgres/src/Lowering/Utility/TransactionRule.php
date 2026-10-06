<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Begin;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\BeginSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Chaining;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Commit;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\CommitSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\PreparedAction;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\PreparedTransaction;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Rollback;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\RollbackSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointAction;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionMode;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the transaction commands.
 *
 * Rule: PG-TRANSACTION-LOWER-001. Scope: `TransactionStmt`,
 * `TransactionStmtLegacy`, `opt_transaction`, `opt_transaction_chain`,
 * `transaction_mode_list_or_empty`, `transaction_mode_list`,
 * `transaction_mode_item`, `iso_level`. Constructors: `Begin`, `Commit`,
 * `Rollback`, `SavepointCommand`, `PreparedTransaction`. WORK and
 * TRANSACTION after a command, SAVEPOINT after RELEASE and ROLLBACK TO, and
 * the comma between modes are noise (UtilityNoise). Termination: the mode
 * list is flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-begin.html, https://www.postgresql.org/docs/17/sql-commit.html,
 * https://www.postgresql.org/docs/17/sql-rollback.html, https://www.postgresql.org/docs/17/sql-savepoint.html,
 * https://www.postgresql.org/docs/17/sql-prepare-transaction.html, https://www.postgresql.org/docs/17/sql-set-transaction.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class TransactionRule
{
    /**
     * The characteristic each mode production names.
     */
    private const MODES = [
        'transaction_mode_item: READ ONLY' => TransactionMode::ReadOnly,
        'transaction_mode_item: READ WRITE' => TransactionMode::ReadWrite,
        'transaction_mode_item: DEFERRABLE' => TransactionMode::Deferrable,
        'transaction_mode_item: NOT DEFERRABLE' => TransactionMode::NotDeferrable,
        'iso_level: READ UNCOMMITTED' => TransactionMode::ReadUncommitted,
        'iso_level: READ COMMITTED' => TransactionMode::ReadCommitted,
        'iso_level: REPEATABLE READ' => TransactionMode::RepeatableRead,
        'iso_level: SERIALIZABLE' => TransactionMode::Serializable,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `TransactionStmt` or `TransactionStmtLegacy`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $names = $this->lowering->names;
        $literals = $this->lowering->literals;

        return match ($form->signature) {
            'TransactionStmtLegacy: BEGIN_P opt_transaction transaction_mode_list_or_empty' => new Begin(BeginSpelling::Begin, $this->optionalModes($form->node(2), $form->node(1))),
            'TransactionStmt: START TRANSACTION transaction_mode_list_or_empty' => new Begin(BeginSpelling::StartTransaction, $this->optionalModes($form->node(2))),
            'TransactionStmtLegacy: END_P opt_transaction opt_transaction_chain' => new Commit(CommitSpelling::End, $this->chaining($form->node(2), $form->node(1))),
            'TransactionStmt: COMMIT opt_transaction opt_transaction_chain' => new Commit(CommitSpelling::Commit, $this->chaining($form->node(2), $form->node(1))),
            'TransactionStmt: ABORT_P opt_transaction opt_transaction_chain' => new Rollback(RollbackSpelling::Abort, $this->chaining($form->node(2), $form->node(1))),
            'TransactionStmt: ROLLBACK opt_transaction opt_transaction_chain' => new Rollback(RollbackSpelling::Rollback, $this->chaining($form->node(2), $form->node(1))),
            'TransactionStmt: SAVEPOINT ColId' => new SavepointCommand(SavepointAction::Define, $names->name($form->node(1))),
            'TransactionStmt: RELEASE SAVEPOINT ColId' => new SavepointCommand(SavepointAction::Release, $names->name($form->node(2))),
            'TransactionStmt: RELEASE ColId' => new SavepointCommand(SavepointAction::Release, $names->name($form->node(1))),
            'TransactionStmt: ROLLBACK opt_transaction TO SAVEPOINT ColId' => $this->rollbackTo($form->node(4), $form->node(1)),
            'TransactionStmt: ROLLBACK opt_transaction TO ColId' => $this->rollbackTo($form->node(3), $form->node(1)),
            'TransactionStmt: PREPARE TRANSACTION Sconst' => new PreparedTransaction(PreparedAction::Prepare, $literals->string($form->node(2))),
            'TransactionStmt: COMMIT PREPARED Sconst' => new PreparedTransaction(PreparedAction::Commit, $literals->string($form->node(2))),
            'TransactionStmt: ROLLBACK PREPARED Sconst' => new PreparedTransaction(PreparedAction::Rollback, $literals->string($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers ROLLBACK TO a savepoint; the optional noise word after ROLLBACK is accepted.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function rollbackTo(Node $name, Node $word): SavepointCommand
    {
        $this->word($word);

        return new SavepointCommand(SavepointAction::RollbackTo, $this->lowering->names->name($name));
    }

    /**
     * Accepts `opt_transaction`, whose WORK or TRANSACTION is noise, and tells whether a word is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function word(Node $word): bool
    {
        $form = $this->lowering->productions->form($word);

        return match ($form->signature) {
            'opt_transaction: WORK', 'opt_transaction: TRANSACTION' => true,
            'opt_transaction:' => false,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_transaction_chain`; no clause is null. The optional noise word before it is accepted.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function chaining(Node $chain, Node $word): ?Chaining
    {
        $this->word($word);
        $form = $this->lowering->productions->form($chain);

        return match ($form->signature) {
            'opt_transaction_chain: AND CHAIN' => Chaining::Chain,
            'opt_transaction_chain: AND NO CHAIN' => Chaining::NoChain,
            'opt_transaction_chain:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `transaction_mode_list_or_empty`; no mode is an empty list. The optional noise word before it is accepted.
     *
     * @return list<TransactionMode>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function optionalModes(Node $list, ?Node $word = null): array
    {
        if ($word !== null) {
            $this->word($word);
        }
        $form = $this->lowering->productions->form($list);

        return match ($form->signature) {
            'transaction_mode_list_or_empty: transaction_mode_list' => $this->modes($form->node(0)),
            'transaction_mode_list_or_empty:' => [],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `transaction_mode_list`: the modes in the order written.
     *
     * @return list<TransactionMode>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function modes(Node $list): array
    {
        $modes = [];
        $spine = ['transaction_mode_list: transaction_mode_item', 'transaction_mode_list: transaction_mode_list , transaction_mode_item', 'transaction_mode_list: transaction_mode_list transaction_mode_item'];
        foreach ($this->lowering->items($list, ...$spine) as $item) {
            $modes[] = $this->mode($item);
        }

        return $modes;
    }

    /**
     * Lowers `transaction_mode_item` with its `iso_level`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function mode(Node $item): TransactionMode
    {
        $form = $this->lowering->productions->form($item);
        if ($form->signature === 'transaction_mode_item: ISOLATION LEVEL iso_level') {
            $form = $this->lowering->productions->form($form->node(2));
        }

        return self::MODES[$form->signature] ?? throw ImplementationGap::production($form);
    }
}
