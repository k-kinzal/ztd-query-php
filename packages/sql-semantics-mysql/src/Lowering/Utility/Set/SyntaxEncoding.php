<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Set;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;

/**
 * Keeps SET encoding conditions from completed input before a later refusal.
 * Only semantic names and notices escape the transient production reader.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxEncoding
{
    /**
     * Retains completed SET encoding lookups before a later malformed item.
     */
    public function completed(Node $node, Lowering $lowering): ?\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure
    {
        if ($node->name !== 'option_value_no_option_type') {
            return null;
        }
        $form = $lowering->form($node);
        if (!in_array($form->signature, [
            'option_value_no_option_type: charset old_or_new_charset_name_or_default',
            'option_value_no_option_type: character_set old_or_new_charset_name_or_default',
            'option_value_no_option_type: NAMES_SYM charset_name_or_default opt_collate',
            'option_value_no_option_type: NAMES_SYM charset_name opt_collate',
        ], true)) {
            return null;
        }
        $item = (new ItemRule($lowering))->connection($form);
        $derivation = new \SqlSemantics\Construction\Derivation(new \SqlSemantics\Contract\AnalysisContext($lowering->profile, [new \SqlSemantics\Statement\Identifier\Name(\SqlSemantics\Platform\MySql\Rules\SessionDatabase::UNNAMED)], complete: false));
        $item->deriveItem($derivation);
        $legacy = in_array($lowering->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        foreach ($derivation->facts()->warnings as $warning) {
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && ($legacy || $warning->phase === \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax)) {
                return $warning;
            }
        }
        return null;
    }

}
