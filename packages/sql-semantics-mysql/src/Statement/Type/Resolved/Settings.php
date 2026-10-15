<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Resolved;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\Session;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramRow;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * The session variables of a MySQL session that change the types statements resolve to.
 *
 * The connection collation types string literals; div_precision_increment widens the scale of a
 * division; the collations of the schemas give a table its default; group_concat_max_len bounds
 * GROUP_CONCAT; character_set_client names the select items named after their text. A statement
 * of a running stored program sees the parameters and local variables in scope there, and the
 * type each stored function returns types its calls; block_encryption_mode sizes the result of
 * AES_ENCRYPT. Calendar mode flags determine whether an ODBC escape denotes a
 * temporal literal or its string operand, including its output type and name. A context without settings resolves as a
 * new session with the server defaults.
 *
 * @visibility public
 * @example Reading the collation string literals take in a new 8.4 session
 *     \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::defaults(\SqlSemantics\Contract\GrammarRelease::MySql847)->connection->name // => 'utf8mb4_0900_ai_ci'
 */
final class Settings implements Session
{
    use Snapshot;

    /**
     * @var array<array-key, Collation> The default collation of each schema, by lower-case name
     */
    public readonly array $schemas;

    /**
     * @var array<array-key, Domain|\SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry>|null The stored types by lower-case name; a Domain alone uses the connection collation for numeric assignment metadata; null when unknown
     */
    public readonly ?array $userVariables;

    /**
     * @var list<ProgramRow> The parameters, local variables and trigger rows a statement of a running stored program sees, the innermost last
     */
    public readonly array $program;

    /**
     * @var array<array-key, Domain> The type each stored function returns, by lower-case `database.name`
     */
    public readonly array $functions;

    /**
     * @var array<array-key, QualifiedName> The table of each open handler, by lowercase alias
     */
    public readonly array $handlers;

    /**
     * @param Collation $connection The collation of string literals (collation_connection)
     * @param int $divPrecisionIncrement The digits a division adds to the scale of its dividend (div_precision_increment)
     * @param Collation|null $server The default collation of a schema the settings do not list (collation_server); the connection collation when null
     * @param array<array-key, Collation> $schemas The default collation of each schema, by name
     * @param int $groupConcatMaxLen The longest result of GROUP_CONCAT in bytes (group_concat_max_len)
     * @param array<array-key, Domain|\SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry>|null $userVariables The stored types by name, optionally including assignment collations; null when the session does not say
     * @param array<int, Domain> $parameters The type of the value bound to each parameter marker, by the position of the marker among the markers
     * @param bool $unsignedSubtraction Whether subtracting from an unsigned integer gives an unsigned integer, as it does unless sql_mode has NO_UNSIGNED_SUBTRACTION
     * @param Charset|null $client The character set statements are read in (character_set_client); null when the session does not say
     * @param list<ProgramRow> $program The parameters, local variables and trigger rows a statement of a running stored program sees, the innermost last; empty outside a program
     * @param array<array-key, Domain> $functions The type each stored function returns, by `database.name`
     * @param bool $derivedMerge Whether eligible derived queries can merge into their enclosing block
     * @param Locale|null $timeNames The locale of the names of months and days (lc_time_names); en_US when null
     * @param string $blockEncryptionMode The mode of AES_ENCRYPT and AES_DECRYPT (block_encryption_mode), whose stream modes do not pad
     * @param bool $strict Whether STRICT_TRANS_TABLES or STRICT_ALL_TABLES is enabled; string calls are reported as nullable in either mode
     * @param bool $noZeroDate Whether temporal literals reject the all-zero date
     * @param bool $noZeroInDate Whether temporal literals reject zero months and days
     * @param bool $allowInvalidDates Whether bounded dates need not exist in the calendar
     * @param bool|null $explicitDefaultsForTimestamp Whether unqualified TIMESTAMP columns are nullable; null when the session does not say
     * @param array<array-key, QualifiedName> $handlers The table names behind open handler aliases
     */
    public function __construct(
        public readonly Collation $connection,
        public readonly int $divPrecisionIncrement = 4,
        public readonly ?Collation $server = null,
        array $schemas = [],
        public readonly int $groupConcatMaxLen = 1024,
        ?array $userVariables = null,
        public readonly array $parameters = [],
        public readonly bool $unsignedSubtraction = true,
        public readonly ?Charset $client = null,
        array $program = [],
        array $functions = [],
        public readonly ?Locale $timeNames = null,
        public readonly string $blockEncryptionMode = 'aes-128-ecb',
        public readonly bool $derivedMerge = true,
        public readonly bool $strict = true,
        public readonly ?bool $explicitDefaultsForTimestamp = null,
        public readonly bool $noZeroDate = true,
        public readonly bool $noZeroInDate = true,
        public readonly bool $allowInvalidDates = false,
        array $handlers = [],
    ) {
        $this->program = Check::listOf($program, ProgramRow::class, 'A running stored program shows rows of names.');
        $routines = [];
        foreach ($functions as $name => $domain) {
            $routines[strtolower((string) $name)] = $domain;
        }
        $this->functions = $routines;
        Check::listOf(array_values($handlers), QualifiedName::class, 'An open handler identifies a table.');
        $opened = [];
        foreach ($handlers as $name => $table) {
            $opened[mb_strtolower((string) $name)] = $table;
        }
        $this->handlers = $opened;
        $lower = [];
        foreach ($schemas as $name => $collation) {
            $lower[strtolower((string) $name)] = $collation;
        }
        $this->schemas = $lower;
        $variables = null;
        foreach ($userVariables ?? [] as $name => $domain) {
            $variables[strtolower((string) $name)] = $domain;
        }
        $this->userVariables = $userVariables === null ? null : ($variables ?? []);
    }

    /**
     * Answers the settings of a new session of a release with the server defaults.
     */
    public static function defaults(GrammarRelease $release): self
    {
        $old = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;

        return new self(Charset::known($old ? 'latin1' : 'utf8mb4')->defaultCollation($release), strict: $release !== GrammarRelease::MySql5651, explicitDefaultsForTimestamp: !$old, noZeroDate: $release !== GrammarRelease::MySql5651, noZeroInDate: $release !== GrammarRelease::MySql5651);
    }

    /**
     * Answers the settings a context resolves with: its session, or the defaults of its release.
     */
    public static function of(AnalysisContext $context): self
    {
        $session = $context->session;

        return $session instanceof self ? $session : self::defaults($context->profile->grammar);
    }

    /**
     * Answers the locale of the names of months and days: lc_time_names, en_US by default.
     */
    public function locale(): Locale
    {
        return $this->timeNames ?? Locale::default();
    }

    /**
     * Answers the default collation of a schema.
     */
    public function schema(string $name): Collation
    {
        return $this->schemas[strtolower($name)] ?? $this->server ?? $this->connection;
    }

    /**
     * Finds the innermost parameter or local variable of a running stored program with a name: its row and its position in the row.
     *
     * @return array{ProgramRow, int}|null
     */
    public function variable(string $name): ?array
    {
        for ($index = count($this->program) - 1; $index >= 0; $index--) {
            $row = $this->program[$index];
            $position = $row->alias === null ? $row->position(new Name($name)) : null;
            if ($position !== null) {
                return [$row, $position];
            }
        }

        return null;
    }

    /**
     * Finds the row of a running trigger NEW or OLD names, compared without regard to letter case.
     */
    public function row(string $alias): ?ProgramRow
    {
        foreach ($this->program as $row) {
            if ($row->alias !== null && strcasecmp($row->alias->value, $alias) === 0) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Answers the type a stored function of a database returns, or null when the settings do not hold it.
     */
    public function function(string $schema, string $name): ?Domain
    {
        return $this->functions[strtolower($schema . '.' . $name)] ?? null;
    }

    /**
     * Answers the table behind an open handler alias, or null when the session does not supply it.
     */
    public function handler(string $name): ?QualifiedName
    {
        return $this->handlers[mb_strtolower($name)] ?? null;
    }
}
