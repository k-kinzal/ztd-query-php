<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Maintenance;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

/**
 * Reports administrative keyword notices at their completed grammar production.
 *
 * A later input refusal does not erase an already read FLUSH, LOCK or database
 * upgrade notice. Repeated cache items retain separate notices on MySQL 5.7.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxKeywords
{
    /**
     * Answers the release-specific notice for a completed administrative option.
     */
    public function completed(Node $node, Lowering $lowering): ?Deprecated
    {
        if (!in_array($node->name, ['alter', 'lock_option', 'flush_option', 'opt_ev_status'], true)) {
            return null;
        }
        $notice = match ($lowering->form($node)->signature) {
            'alter: ALTER DATABASE ident UPGRADE_SYM DATA_SYM DIRECTORY_SYM NAME_SYM' => Deprecated::UpgradeDatabaseName,
            'lock_option: LOW_PRIORITY WRITE_SYM' => Deprecated::LowPriorityWrite,
            'flush_option: QUERY_SYM CACHE_SYM' => Deprecated::FlushQueryCache,
            'opt_ev_status: DISABLE_SYM ON SLAVE', 'opt_ev_status: DISABLE_SYM ON_SYM SLAVE' => Deprecated::DisableOnSlave,
            default => null,
        };
        return $notice?->warnedIn($lowering->profile->grammar) === true ? $notice : null;
    }
}
