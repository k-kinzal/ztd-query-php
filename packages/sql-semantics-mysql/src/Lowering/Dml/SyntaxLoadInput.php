<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Identifiers;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\ZeroFileCount;

/**
 * Preserves LOAD input-count checks before subsequent clauses are read.
 *
 * MySQL 8.4 and later require a positive count; earlier releases accept zero.
 * The same IDENT_sys production occurs on both sides of that behavior change.
 * Observed on MySQL 8.0 through 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxLoadInput
{
    /**
     * Preserves charset warnings and count refusals when later LOAD operands fail.
     */
    public function completed(Node $node, Lowering $lowering): \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure|null
    {
        $refusal = $this->rejected($node, $lowering);
        if ($refusal !== null) {
            return new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($refusal->problem, true);
        }
        if ($node->name !== 'opt_load_data_charset') {
            return null;
        }
        $name = (new FormatRule($lowering))->charset($node)?->name?->value;
        $notice = match (strtolower($name ?? '')) {
            'utf8' => \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::Utf8Alias,
            'utf8mb3' => \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::Utf8mb3,
            'ucs2' => \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::Ucs2,
            default => null,
        };
        return $notice?->warnedIn($lowering->profile->grammar) === true ? $notice : null;
    }

    /**
     * Refuses a zero count only after a valid COUNT spelling in the newer form.
     */
    public function rejected(Node $node, Lowering $lowering): ?RejectedInput
    {
        if (!in_array($lowering->profile->grammar, [GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910], true)) {
            return null;
        }
        if ($node->name !== 'opt_source_count' || $lowering->form($node)->signature !== 'opt_source_count: IDENT_sys NUM') {
            return null;
        }
        $form = $lowering->form($node);
        $word = (new Identifiers())->decode($form->node(0)->tokens()[0]->text);
        $number = $form->token(1);
        return strcasecmp($word, 'COUNT') === 0 && trim($number->text, '0') === ''
            ? new RejectedInput(new ZeroFileCount($number->offset), $number->end()) : null;
    }
}
