<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Analyze;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Reindex;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Vacuum;
use SqlSemantics\Platform\Sqlite\Statement\Type\SignedNumber;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the administration commands VACUUM, PRAGMA, REINDEX and ANALYZE.
 *
 * Rule: SQLITE-MAINTENANCE-LOWER-001. Scope: the `cmd` productions of VACUUM,
 * PRAGMA, REINDEX and ANALYZE, with vinto and nmnum. Constructors: Vacuum,
 * Pragma, Reindex, Analyze. The two value notations of a pragma lower to the
 * same request; `=` and the parentheses are declared noise. No fact is derived
 * here. Terminates: fixed number of children.
 * Source: https://sqlite.org/lang_vacuum.html, https://sqlite.org/pragma.html,
 * https://sqlite.org/lang_reindex.html, https://sqlite.org/lang_analyze.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class MaintenanceRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an administration command, or answers null for a command of another family.
     */
    public function command(Form $form): ?Statement
    {
        return match ($form->signature) {
            'cmd: VACUUM vinto' => new Vacuum(null, $this->into($form->node(1))),
            'cmd: VACUUM nm vinto' => new Vacuum($this->lowering->names->name($form->node(1)), $this->into($form->node(2))),
            'cmd: PRAGMA nm dbnm' => new Pragma($this->lowering->names->scoped($form->node(1), $form->node(2))),
            'cmd: PRAGMA nm dbnm EQ nmnum', 'cmd: PRAGMA nm dbnm LP nmnum RP' => new Pragma($this->lowering->names->scoped($form->node(1), $form->node(2)), $this->value($form->node(4))),
            'cmd: PRAGMA nm dbnm EQ minus_num', 'cmd: PRAGMA nm dbnm LP minus_num RP' => new Pragma($this->lowering->names->scoped($form->node(1), $form->node(2)), $this->lowering->typeNames->minusNumber($form->node(4))),
            'cmd: REINDEX' => new Reindex(),
            'cmd: REINDEX nm dbnm' => new Reindex($this->lowering->names->scoped($form->node(1), $form->node(2))),
            'cmd: ANALYZE' => new Analyze(),
            'cmd: ANALYZE nm dbnm' => new Analyze($this->lowering->names->scoped($form->node(1), $form->node(2))),
            default => null,
        };
    }

    /**
     * Lowers the optional target file expression of VACUUM.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function into(Node $into): ?Scalar
    {
        $form = $this->lowering->productions->form($into);

        return match ($form->signature) {
            'vinto:' => null,
            'vinto: INTO expr' => $this->lowering->expressions->expression($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a pragma value that is not a negative number.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function value(Node $value): SignedNumber|Name|PragmaKeyword
    {
        $form = $this->lowering->productions->form($value);

        return match ($form->signature) {
            'nmnum: plus_num' => $this->lowering->typeNames->plusNumber($form->node(0)),
            'nmnum: nm' => $this->lowering->names->name($form->node(0)),
            'nmnum: ON' => PragmaKeyword::On,
            'nmnum: DELETE' => PragmaKeyword::Delete,
            'nmnum: DEFAULT' => PragmaKeyword::Default,
            default => throw ImplementationGap::production($form),
        };
    }
}
