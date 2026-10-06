<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Query;

use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the commands that read or write rows.
 *
 * Rule: SQLITE-QUERY-COMMANDS-001. Scope: the `cmd` productions for SELECT,
 * INSERT, REPLACE, UPDATE, DELETE and CREATE TRIGGER. Every other command
 * belongs to the definition commands. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class QueryCommands
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a command of this family, or answers null for a command of another family.
     */
    public function command(Form $form): ?Statement
    {
        return match ($form->signature) {
            'cmd: select' => $this->lowering->selects->select($form->node(0)),
            'cmd: createkw trigger_decl BEGIN trigger_cmd_list END' => $this->lowering->triggers->create($form->node(1), $form->node(3)),
            default => $this->lowering->mutations->command($form),
        };
    }
}
