<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Transaction;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\ReleaseSavepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Rollback;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\RollbackToSavepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\StartTransaction;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the transaction statements: BEGIN, START TRANSACTION, COMMIT, ROLLBACK and the savepoint statements.
 *
 * Rule: MYSQL-TRANSACTION-001. Scope: begin (5.6, 5.7), begin_stmt (8.0 and
 * later), start, opt_start_transaction_option_list,
 * start_transaction_option_list, start_transaction_option, commit,
 * rollback, savepoint, release, opt_work, opt_chain, opt_release,
 * opt_savepoint. WORK and the SAVEPOINT of ROLLBACK TO are optional words
 * (ServerNoise). READ ONLY with READ WRITE in START TRANSACTION, and AND
 * CHAIN with RELEASE in COMMIT or ROLLBACK, are syntax errors the server
 * raises in the grammar actions (sql_yacc.yy `start`, `commit`, `rollback`)
 * and are rejected as such. Constructs: Begin, StartTransaction, Commit,
 * Rollback, RollbackToSavepoint, Savepoint, ReleaseSavepoint. Terminates:
 * the option list is flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/commit.html,
 * https://dev.mysql.com/doc/refman/8.4/en/savepoint.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class TransactionRule
{
    /**
     * The characteristics of START TRANSACTION, by production.
     */
    private const CHARACTERISTICS = [
        'start_transaction_option: WITH CONSISTENT_SYM SNAPSHOT_SYM' => TransactionCharacteristic::WithConsistentSnapshot,
        'start_transaction_option: READ_SYM ONLY_SYM' => TransactionCharacteristic::ReadOnly,
        'start_transaction_option: READ_SYM WRITE_SYM' => TransactionCharacteristic::ReadWrite,
    ];

    /**
     * The completion choices of COMMIT and ROLLBACK, by production: true, false or null when absent.
     */
    private const COMPLETIONS = [
        'opt_chain:' => null, 'opt_chain: AND_SYM NO_SYM CHAIN_SYM' => false, 'opt_chain: AND_SYM CHAIN_SYM' => true,
        'opt_release:' => null, 'opt_release: RELEASE_SYM' => true, 'opt_release: NO_SYM RELEASE_SYM' => false,
    ];

    /**
     * The optional words that hold no operand.
     */
    private const WORDS = ['opt_work:' => true, 'opt_work: WORK_SYM' => true, 'opt_savepoint:' => true, 'opt_savepoint: SAVEPOINT_SYM' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a transaction statement.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When the statement requests choices that exclude each other
     */
    public function statement(Form $form): Statement
    {
        $names = $this->lowering->names;

        return match ($form->signature) {
            'begin: BEGIN_SYM opt_work', 'begin_stmt: BEGIN_SYM opt_work' => $this->begin($form->node(1)),
            'start: START_SYM TRANSACTION_SYM opt_start_transaction_option_list' => $this->start($form->node(2)),
            'commit: COMMIT_SYM opt_work opt_chain opt_release' => $this->completion($form, true),
            'rollback: ROLLBACK_SYM opt_work opt_chain opt_release' => $this->completion($form, false),
            'rollback: ROLLBACK_SYM opt_work TO_SYM opt_savepoint ident' => $this->rollbackTo($form),
            'savepoint: SAVEPOINT_SYM ident' => new Savepoint($names->identifier($form->node(1))),
            'release: RELEASE_SYM SAVEPOINT_SYM ident' => new ReleaseSavepoint($names->identifier($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers BEGIN [WORK].
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function begin(Node $work): Begin
    {
        $this->word($work);

        return new Begin();
    }

    /**
     * Lowers START TRANSACTION from its option list: a node of `opt_start_transaction_option_list`.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When READ ONLY and READ WRITE are both written
     */
    public function start(Node $options): StartTransaction
    {
        $form = $this->lowering->form($options);
        $characteristics = [];
        if ($form->signature === 'opt_start_transaction_option_list: start_transaction_option_list') {
            $list = $form->node(0);
            $this->lowering->names->claimed($this->lowering->form($list), [
                'start_transaction_option_list: start_transaction_option', 'start_transaction_option_list: start_transaction_option_list , start_transaction_option',
            ]);
            foreach ((new Lists())->items($list) as $item) {
                $option = $this->lowering->form($item);
                $characteristics[] = self::CHARACTERISTICS[$option->signature] ?? throw ImplementationGap::production($option);
            }
        } elseif ($form->signature !== 'opt_start_transaction_option_list:') {
            throw ImplementationGap::production($form);
        }
        if (in_array(TransactionCharacteristic::ReadOnly, $characteristics, true) && in_array(TransactionCharacteristic::ReadWrite, $characteristics, true)) {
            throw new AnalysisException('Syntax error: READ ONLY and READ WRITE exclude each other in START TRANSACTION.');
        }

        return new StartTransaction($characteristics);
    }

    /**
     * Lowers COMMIT or ROLLBACK with their completion choices.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When AND CHAIN and RELEASE are both written
     */
    public function completion(Form $form, bool $commit): Commit|Rollback
    {
        $this->word($form->node(1));
        $chain = $this->choice($form->node(2));
        $release = $this->choice($form->node(3));
        if ($chain === true && $release === true) {
            throw new AnalysisException('Syntax error: AND CHAIN and RELEASE exclude each other.');
        }

        return $commit ? new Commit($chain, $release) : new Rollback($chain, $release);
    }

    /**
     * Lowers ROLLBACK TO SAVEPOINT.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function rollbackTo(Form $form): RollbackToSavepoint
    {
        $this->word($form->node(1));
        $this->word($form->node(3));

        return new RollbackToSavepoint($this->lowering->names->identifier($form->node(4)));
    }

    /**
     * Lowers a completion choice: a node of `opt_chain` or `opt_release`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function choice(Node $choice): ?bool
    {
        $form = $this->lowering->form($choice);
        if (!array_key_exists($form->signature, self::COMPLETIONS)) {
            throw ImplementationGap::production($form);
        }

        return self::COMPLETIONS[$form->signature];
    }

    /**
     * Confirms an optional word that holds no operand: a node of `opt_work` or `opt_savepoint`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function word(Node $word): void
    {
        $form = $this->lowering->form($word);
        if (!isset(self::WORDS[$form->signature])) {
            throw ImplementationGap::production($form);
        }
    }
}
