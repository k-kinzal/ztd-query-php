<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Transaction;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Server\Magnitudes;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaCommit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEnd;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEndOption;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaPrepare;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRecover;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRollback;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStart;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStartOption;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the XA transaction statements.
 *
 * Rule: MYSQL-XA-001. Scope: xa, xid, begin_or_start, opt_join_or_resume,
 * opt_one_phase, opt_suspend, opt_migrate (5.6), opt_convert_xid (5.7 and
 * later). BEGIN and START are synonyms in XA START (ServerNoise). A global
 * transaction identifier or branch qualifier longer than 64 bytes, and a
 * format identifier above 2^63-1, are syntax errors the server raises in the
 * `xid` action (MYSQL_YYABORT_UNLESS) and are rejected as such. Constructs:
 * XaStart, XaEnd, XaPrepare, XaCommit, XaRollback, XaRecover, Xid.
 * Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class XaRule
{
    /**
     * The option productions, by the option they select; null for an absent option.
     */
    private const OPTIONS = [
        'opt_join_or_resume:' => null, 'opt_join_or_resume: JOIN_SYM' => XaStartOption::Join, 'opt_join_or_resume: RESUME_SYM' => XaStartOption::Resume,
        'opt_suspend:' => null, 'opt_suspend: SUSPEND_SYM' => XaEndOption::Suspend,
        'opt_suspend: SUSPEND_SYM FOR_SYM MIGRATE_SYM' => XaEndOption::SuspendForMigrate,
    ];

    /**
     * The flag productions, by whether they write the words.
     */
    private const FLAGS = [
        'opt_one_phase:' => false, 'opt_one_phase: ONE_SYM PHASE_SYM' => true, 'opt_convert_xid:' => false, 'opt_convert_xid: CONVERT_SYM XID_SYM' => true,
        'begin_or_start: BEGIN_SYM' => true, 'begin_or_start: START_SYM' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an XA statement: a node of `xa`.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When an identifier part exceeds its limit
     */
    public function statement(Form $form): Statement
    {
        return match ($form->signature) {
            'xa: XA_SYM begin_or_start xid opt_join_or_resume' => $this->start($form),
            'xa: XA_SYM END xid opt_suspend' => new XaEnd($this->xid($form->node(2)), $this->endOption($form->node(3))),
            'xa: XA_SYM PREPARE_SYM xid' => new XaPrepare($this->xid($form->node(2))),
            'xa: XA_SYM COMMIT_SYM xid opt_one_phase' => new XaCommit($this->xid($form->node(2)), $this->flag($form->node(3))),
            'xa: XA_SYM ROLLBACK_SYM xid' => new XaRollback($this->xid($form->node(2))),
            'xa: XA_SYM RECOVER_SYM' => new XaRecover(),
            'xa: XA_SYM RECOVER_SYM opt_convert_xid' => new XaRecover($this->flag($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers XA START or XA BEGIN.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When an identifier part exceeds its limit
     */
    public function start(Form $form): XaStart
    {
        $this->flag($form->node(1));
        $option = $this->option($form->node(3));

        return new XaStart($this->xid($form->node(2)), $option instanceof XaStartOption ? $option : null);
    }

    /**
     * Lowers the option of XA END: a node of `opt_suspend`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function endOption(Node $option): ?XaEndOption
    {
        $form = $this->lowering->form($option);
        if ($form->signature === 'opt_suspend: SUSPEND_SYM opt_migrate') {
            $migrate = $this->lowering->form($form->node(1));

            return match ($migrate->signature) {
                'opt_migrate:' => XaEndOption::Suspend,
                'opt_migrate: FOR_SYM MIGRATE_SYM' => XaEndOption::SuspendForMigrate,
                default => throw ImplementationGap::production($migrate),
            };
        }
        $lowered = $this->option($option);

        return $lowered instanceof XaEndOption ? $lowered : null;
    }

    /**
     * Lowers an optional option: a node of `opt_join_or_resume` or `opt_suspend`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function option(Node $option): XaStartOption|XaEndOption|null
    {
        $form = $this->lowering->form($option);
        if (!array_key_exists($form->signature, self::OPTIONS)) {
            throw ImplementationGap::production($form);
        }

        return self::OPTIONS[$form->signature];
    }

    /**
     * Lowers a flag: a node of `opt_one_phase`, `opt_convert_xid` or `begin_or_start`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function flag(Node $flag): bool
    {
        $form = $this->lowering->form($flag);

        return self::FLAGS[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers an XA transaction identifier: a node of `xid`.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When a part exceeds its limit
     */
    public function xid(Node $xid): Xid
    {
        $form = $this->lowering->form($xid);
        $literals = $this->lowering->literals;
        [$transaction, $branch, $format] = match ($form->signature) {
            'xid: text_string' => [$literals->text($form->node(0)), null, null],
            'xid: text_string , text_string' => [$literals->text($form->node(0)), $literals->text($form->node(2)), null],
            'xid: text_string , text_string , ulong_num' => [$literals->text($form->node(0)), $literals->text($form->node(2)), $this->lowering->numbers->numeral($form->node(4))],
            default => throw ImplementationGap::production($form),
        };
        $this->limits($transaction, $branch, $format);

        return new Xid($transaction, $branch, $format);
    }

    /**
     * Rejects identifier parts the server rejects while it parses.
     *
     * @throws AnalysisException When a part exceeds its limit
     */
    public function limits(Text $transaction, ?Text $branch, ?Numeral $format): void
    {
        $magnitudes = new Magnitudes();
        if ($magnitudes->bytes($transaction) > 64 || ($branch !== null && $magnitudes->bytes($branch) > 64)) {
            throw new AnalysisException('Syntax error: an XA transaction identifier part holds at most 64 bytes.');
        }
        if ($format !== null && !$magnitudes->format($format, $this->lowering->profile->grammar)) {
            throw new AnalysisException('Syntax error: an XA format identifier is at most 9223372036854775807.');
        }
    }
}
