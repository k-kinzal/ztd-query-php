<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;

/**
 * Lowers the optional keyword groups that only state a yes or a no.
 *
 * Rule: SQLITE-FLAG-001. Scope: temp, ifnotexists. Each is true when its
 * keywords are written. Source: https://sqlite.org/lang_createtrigger.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class FlagRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Tells whether TEMP or TEMPORARY is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function temporary(Node $temp): bool
    {
        $form = $this->lowering->productions->form($temp);

        return match ($form->signature) {
            'temp:' => false,
            'temp: TEMP' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Tells whether IF NOT EXISTS is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function ifNotExists(Node $clause): bool
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'ifnotexists:' => false,
            'ifnotexists: IF NOT EXISTS' => true,
            default => throw ImplementationGap::production($form),
        };
    }
}
