<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View\Definer;

use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewDefiner;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;

/**
 * Resolves a written definer at the native account boundary.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ViewDefinerLowering
{
    /**
     * Checks authored legacy widths before normalizing the account identity.
     */
    public function lower(?Account $account, GrammarRelease $release): ?ViewDefiner
    {
        if ($account === null || $account instanceof CurrentUser) {
            return null;
        }
        Check::input($account instanceof AccountName, 'A view definer must select the current user or a named account.');
        if ($account->host !== null && ($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744)) {
            $maximum = $release === GrammarRelease::MySql5651 ? 16 : 32;
            Check::input(preg_match('/\A.{0,' . $maximum . '}\z/usD', $account->user->value) === 1, 'An explicit MySQL definer user exceeds the native character limit of this release.');
            Check::input(strlen($account->host->value) <= 60, 'A MySQL 5.6 or 5.7 view definer host permits at most 60 bytes.');
        }

        return new ViewDefiner($account->user->value, $account->host->value ?? '%');
    }
}
