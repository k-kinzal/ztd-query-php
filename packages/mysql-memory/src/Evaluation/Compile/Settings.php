<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Session\SqlModes;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings as Resolution;

/**
 * The session settings that decide how a statement is resolved: what the server reads when it prepares a statement.
 *
 * @visibility MySqlMemory
 */
final class Settings
{
    /**
     * @param Collation $connectionCollation The collation of string literals (collation_connection)
     * @param SqlModes $modes The sql_mode of the session
     * @param int $divPrecisionIncrement The digits a decimal division adds to the scale of the dividend
     * @param string $database The current database, or the empty string for none
     * @param string $version The server version the session reports
     * @param Resolution|null $resolution The session as SQL Semantics resolves types in it; the server defaults when null
     */
    public function __construct(
        public readonly Collation $connectionCollation,
        public readonly SqlModes $modes,
        public readonly int $divPrecisionIncrement = 4,
        public readonly string $database = '',
        public readonly string $version = '8.4.7',
        public readonly ?Resolution $resolution = null,
    ) {
    }

    /**
     * Answers the session as SQL Semantics resolves types in it.
     */
    public function resolution(): Resolution
    {
        return $this->resolution ?? new Resolution($this->connectionCollation, $this->divPrecisionIncrement);
    }

    /**
     * Answers the release whose character sets and collations the session has.
     */
    public function release(): GrammarRelease
    {
        return GrammarRelease::tryFrom('mysql-' . $this->version) ?? GrammarRelease::MySql847;
    }

    /**
     * Tells whether the session emulates MySQL 5.6 or 5.7.
     */
    public function legacy(): bool
    {
        return in_array($this->release(), [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
    }
}
