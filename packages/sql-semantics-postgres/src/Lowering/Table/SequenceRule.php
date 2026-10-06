<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\AlterSequence;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\CreateSequence;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceAs;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceFlag;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceFlagKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceNumberKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceOption;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceReference;

/**
 * Lowers sequence options.
 *
 * Rule: PG-SEQUENCE-OPTION-LOWER-001. Scope: `OptSeqOptList`,
 * `OptParenthesizedSeqOptList`, `SeqOptList`, `SeqOptElem`, `opt_by`. The
 * optional BY of INCREMENT and WITH of START and RESTART are noise
 * (TableNoise, LeafNoise). Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-createsequence.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class SequenceRule
{
    /**
     * The flag each operand-less `SeqOptElem` production writes.
     */
    private const FLAGS = [
        'SeqOptElem: CYCLE' => SequenceFlagKind::Cycle, 'SeqOptElem: NO CYCLE' => SequenceFlagKind::NoCycle,
        'SeqOptElem: NO MAXVALUE' => SequenceFlagKind::NoMaxValue, 'SeqOptElem: NO MINVALUE' => SequenceFlagKind::NoMinValue,
        'SeqOptElem: RESTART' => SequenceFlagKind::Restart, 'SeqOptElem: LOGGED' => SequenceFlagKind::Logged,
        'SeqOptElem: UNLOGGED' => SequenceFlagKind::Unlogged,
    ];

    /**
     * The option and the position of the number of each numeric `SeqOptElem` production.
     */
    private const NUMBERS = [
        'SeqOptElem: CACHE NumericOnly' => [SequenceNumberKind::Cache, 1],
        'SeqOptElem: INCREMENT opt_by NumericOnly' => [SequenceNumberKind::Increment, 2],
        'SeqOptElem: MAXVALUE NumericOnly' => [SequenceNumberKind::MaxValue, 1],
        'SeqOptElem: MINVALUE NumericOnly' => [SequenceNumberKind::MinValue, 1],
        'SeqOptElem: START opt_with NumericOnly' => [SequenceNumberKind::Start, 2],
        'SeqOptElem: RESTART opt_with NumericOnly' => [SequenceNumberKind::Restart, 2],
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `CreateSeqStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function create(Node $statement): CreateSequence
    {
        $form = $this->lowering->productions->form($statement);
        $at = match ($form->signature) {
            'CreateSeqStmt: CREATE OptTemp SEQUENCE qualified_name OptSeqOptList' => 0,
            'CreateSeqStmt: CREATE OptTemp SEQUENCE IF_P NOT EXISTS qualified_name OptSeqOptList' => 3,
            default => throw ImplementationGap::production($form),
        };

        return new CreateSequence(
            $this->lowering->names->qualified($form->node(3 + $at)),
            $this->optional($form->node(4 + $at)),
            (new CreateTableRule($this->lowering))->persistence($form->node(1)),
            $at !== 0,
        );
    }

    /**
     * Lowers `AlterSeqStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alter(Node $statement): AlterSequence
    {
        $form = $this->lowering->productions->form($statement);
        $at = match ($form->signature) {
            'AlterSeqStmt: ALTER SEQUENCE qualified_name SeqOptList' => 0,
            'AlterSeqStmt: ALTER SEQUENCE IF_P EXISTS qualified_name SeqOptList' => 2,
            default => throw ImplementationGap::production($form),
        };

        return new AlterSequence($this->lowering->names->qualified($form->node(2 + $at)), $this->options($form->node(3 + $at)), $at !== 0);
    }

    /**
     * Lowers `OptParenthesizedSeqOptList`.
     *
     * @return list<SequenceOption>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function parenthesized(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'OptParenthesizedSeqOptList: ( SeqOptList )' => $this->options($form->node(1)),
            'OptParenthesizedSeqOptList:' => [],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `OptSeqOptList`.
     *
     * @return list<SequenceOption>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function optional(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'OptSeqOptList: SeqOptList' => $this->options($form->node(0)),
            'OptSeqOptList:' => [],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `SeqOptList`.
     *
     * @return list<SequenceOption>
     */
    public function options(Node $list): array
    {
        $options = [];
        foreach ($this->lowering->items($list, 'SeqOptList: SeqOptElem', 'SeqOptList: SeqOptList SeqOptElem') as $item) {
            $options[] = $this->option($item);
        }

        return $options;
    }

    /**
     * Lowers `SeqOptElem`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function option(Node $option): SequenceOption
    {
        $form = $this->lowering->productions->form($option);
        if (isset(self::FLAGS[$form->signature])) {
            return new SequenceFlag(self::FLAGS[$form->signature]);
        }
        if (isset(self::NUMBERS[$form->signature])) {
            [$kind, $at] = self::NUMBERS[$form->signature];
            if ($kind === SequenceNumberKind::Increment) {
                $this->noise($form->node(1), 'opt_by: BY', 'opt_by:');
            }

            return new SequenceNumber($kind, $this->lowering->literals->signed($form->node($at)));
        }

        return match ($form->signature) {
            'SeqOptElem: AS SimpleTypename' => new SequenceAs($this->lowering->types->simple($form->node(1))),
            'SeqOptElem: OWNED BY any_name' => new SequenceReference(true, $this->lowering->names->dotted($form->node(2))),
            'SeqOptElem: SEQUENCE NAME_P any_name' => new SequenceReference(false, $this->lowering->names->dotted($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Checks a production that only writes noise words: it must be one of the given ones.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function noise(Node $words, string ...$signatures): void
    {
        $form = $this->lowering->productions->form($words);
        if (!in_array($form->signature, $signatures, true)) {
            throw ImplementationGap::production($form);
        }
    }
}
