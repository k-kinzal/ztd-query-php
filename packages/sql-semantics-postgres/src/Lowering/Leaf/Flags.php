<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AddOrDrop;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Option\NormalForm;
use SqlSemantics\Platform\PostgreSql\Statement\Option\XmlOption;

/**
 * Lowers the optional keywords several families share.
 *
 * Rule: PG-FLAG-001. Scope: `opt_or_replace`, `opt_concurrently`,
 * `opt_with`, `opt_as`, `opt_table`, `opt_column`, `opt_default`,
 * `opt_nowait`, `opt_procedural`, `opt_set_data`, `opt_drop_behavior`,
 * `add_drop`, `document_or_content`, `unicode_normal_form`. A flag is whether
 * its keywords are written; a choice is an enum case. The noise words among
 * them (`opt_with`, `opt_as`, `opt_table`, `opt_column`, `opt_procedural`,
 * `opt_set_data`) are listed with their citations in the leaf noise table,
 * and a command does not keep them. Source: the synopsis of each command in
 * https://www.postgresql.org/docs/17/sql-commands.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Flags
{
    /**
     * Whether each flag production writes its keywords.
     */
    private const PRESENT = [
        'opt_or_replace: OR REPLACE' => true, 'opt_or_replace:' => false,
        'opt_concurrently: CONCURRENTLY' => true, 'opt_concurrently:' => false,
        'opt_with: WITH' => true, 'opt_with: WITH_LA' => true, 'opt_with:' => false,
        'opt_as: AS' => true, 'opt_as:' => false,
        'opt_table: TABLE' => true, 'opt_table:' => false,
        'opt_column: COLUMN' => true, 'opt_column:' => false,
        'opt_default: DEFAULT' => true, 'opt_default:' => false,
        'opt_nowait: NOWAIT' => true, 'opt_nowait:' => false,
        'opt_procedural: PROCEDURAL' => true, 'opt_procedural:' => false,
        'opt_set_data: SET DATA_P' => true, 'opt_set_data:' => false,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Tells whether an optional keyword is written: `opt_or_replace`, `opt_concurrently`, `opt_with`, `opt_as`, `opt_table`, `opt_column`, `opt_default`, `opt_nowait`, `opt_procedural` or `opt_set_data`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function present(Node $flag): bool
    {
        $form = $this->lowering->productions->form($flag);

        return self::PRESENT[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers `opt_drop_behavior`; no keyword is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function dropBehavior(Node $behavior): ?DropBehavior
    {
        $form = $this->lowering->productions->form($behavior);

        return match ($form->signature) {
            'opt_drop_behavior: CASCADE' => DropBehavior::Cascade,
            'opt_drop_behavior: RESTRICT' => DropBehavior::Restrict,
            'opt_drop_behavior:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `add_drop`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function addOrDrop(Node $choice): AddOrDrop
    {
        $form = $this->lowering->productions->form($choice);

        return match ($form->signature) {
            'add_drop: ADD_P' => AddOrDrop::Add,
            'add_drop: DROP' => AddOrDrop::Drop,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `document_or_content`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function xmlOption(Node $option): XmlOption
    {
        $form = $this->lowering->productions->form($option);

        return match ($form->signature) {
            'document_or_content: DOCUMENT_P' => XmlOption::Document,
            'document_or_content: CONTENT_P' => XmlOption::Content,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `unicode_normal_form`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function normalForm(Node $form): NormalForm
    {
        $production = $this->lowering->productions->form($form);

        return match ($production->signature) {
            'unicode_normal_form: NFC' => NormalForm::Nfc,
            'unicode_normal_form: NFD' => NormalForm::Nfd,
            'unicode_normal_form: NFKC' => NormalForm::Nfkc,
            'unicode_normal_form: NFKD' => NormalForm::Nfkd,
            default => throw ImplementationGap::production($production),
        };
    }
}
