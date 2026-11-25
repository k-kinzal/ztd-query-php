<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeLocalNames;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The resolved identity of a named account assigned to a persistent view.
 *
 * @visibility public
 * @example Resolving a named view definer
 *     $account = new \SqlSemantics\Platform\MySql\Ir\Schema\View\ViewDefiner('Reporter', 'LOCALHOST');
 *     [$account->user, $account->host] // => ['Reporter', 'localhost']
 */
final class ViewDefiner
{
    use Snapshot;

    /**
     * The case-sensitive user component of the declared account.
     */
    public readonly string $user;

    /**
     * The host component under the native name comparison.
     */
    public readonly string $host;

    /**
     * Establishes the distinct user and host components of the native identity.
     */
    public function __construct(string $user, string $host = '%')
    {
        $user = trim($user, ' ');
        $host = trim($host, ' ');
        Check::input(preg_match('/\A[\x{1}-\x{FFFF}]{0,32}\z/uD', $user) === 1, 'A view definer user requires at most 32 BMP characters without NUL.');
        Check::input(preg_match('/\A[\x{1}-\x{FFFF}]{0,255}\z/uD', $host) === 1, 'A view definer host requires at most 255 BMP characters without NUL.');
        $this->user = $user;
        $this->host = (new NativeLocalNames())->key($host);
    }

    /**
     * Checks the account widths where the native definition is installed.
     */
    public function checkRelease(GrammarRelease $release): void
    {
        Check::input(
            $release !== GrammarRelease::MySql5651 || preg_match('/\A.{0,16}\z/usD', $this->user) === 1,
            'A MySQL 5.6 view definer user permits at most 16 characters.'
        );
        Check::input(
            ($release !== GrammarRelease::MySql5651 && $release !== GrammarRelease::MySql5744)
                || preg_match('/\A.{0,60}\z/usD', $this->host) === 1,
            'A MySQL 5.6 or 5.7 view definer host permits at most 60 characters.'
        );
    }

    /**
     * Emits the two independently quoted account components.
     */
    public function accountSql(): string
    {
        return (new TextLiteral($this->user))->toString() . '@' . (new TextLiteral($this->host))->toString();
    }
}
