<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalSet;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceEvent;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceMatch;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;

/**
 * Settles native relation effects before the original reference syntax is discarded.
 * @visibility SqlSemantics
 */
final class ForeignKeyEffectsLowering
{
    /**
     * Applies the release-specific defaults and MATCH behavior.
     */
    public function lower(References $reference, GrammarRelease $release): ForeignKeyEffects
    {
        $legacy = (new ForeignKeyRemovalSet())->legacy($release);
        $default = $legacy ? ForeignKeyAction::Restrict : ForeignKeyAction::NoAction;
        if ($legacy && $reference->match !== null) {
            return new ForeignKeyEffects($default, $default);
        }
        $match = match ($reference->match) {
            null, ReferenceMatch::Simple => ForeignKeyMatch::None,
            ReferenceMatch::Full => ForeignKeyMatch::Full,
            ReferenceMatch::Partial => ForeignKeyMatch::Partial,
        };
        $update = $delete = $default;
        foreach ($reference->actions as $action) {
            $selected = ForeignKeyAction::from($action->option->value);
            match ($action->event) {
                ReferenceEvent::Update => $update = $selected,
                ReferenceEvent::Delete => $delete = $selected,
            };
        }

        return new ForeignKeyEffects($update, $delete, $match);
    }
}
