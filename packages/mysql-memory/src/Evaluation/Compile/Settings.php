<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use SqlSemantics\Contract\GrammarRelease;
use MySqlMemory\Session\SqlModes;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

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
     */
    public function __construct(
        public readonly Collation $connectionCollation,
        public readonly SqlModes $modes,
        public readonly int $divPrecisionIncrement = 4,
        public readonly string $database = '',
        public readonly string $version = '8.4.7',
    ) {
    }

    /**
     * Answers the release whose character sets and collations the session has.
     */
    public function release(): GrammarRelease
    {
        return GrammarRelease::tryFrom('mysql-' . $this->version) ?? GrammarRelease::MySql847;
    }
}
