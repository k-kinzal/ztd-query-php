<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Resolved;

use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\Session;

/**
 * The session variables of a MySQL session that change the types statements resolve to.
 *
 * The connection collation types string literals; div_precision_increment widens the scale of a
 * division; the collations of the schemas give a table its default; group_concat_max_len bounds
 * GROUP_CONCAT. A context without settings resolves as a new session with the server defaults.
 *
 * @visibility public
 * @example Reading the collation string literals take in a new 8.4 session
 *     \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::defaults(\SqlSemantics\Contract\GrammarRelease::MySql847)->connection->name // => 'utf8mb4_0900_ai_ci'
 */
final class Settings implements Session
{
    use Snapshot;

    /**
     * @var array<string, Collation> The default collation of each schema, by lower-case name
     */
    public readonly array $schemas;

    /**
     * @param Collation $connection The collation of string literals (collation_connection)
     * @param int $divPrecisionIncrement The digits a division adds to the scale of its dividend (div_precision_increment)
     * @param Collation|null $server The default collation of a schema the settings do not list (collation_server); the connection collation when null
     * @param array<string, Collation> $schemas The default collation of each schema, by name
     * @param int $groupConcatMaxLen The longest result of GROUP_CONCAT in bytes (group_concat_max_len)
     */
    public function __construct(
        public readonly Collation $connection,
        public readonly int $divPrecisionIncrement = 4,
        public readonly ?Collation $server = null,
        array $schemas = [],
        public readonly int $groupConcatMaxLen = 1024,
    ) {
        $lower = [];
        foreach ($schemas as $name => $collation) {
            $lower[strtolower($name)] = $collation;
        }
        $this->schemas = $lower;
    }

    /**
     * Answers the settings of a new session of a release with the server defaults.
     */
    public static function defaults(GrammarRelease $release): self
    {
        $old = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;

        return new self(Charset::known($old ? 'latin1' : 'utf8mb4')->defaultCollation($release));
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
     * Answers the default collation of a schema.
     */
    public function schema(string $name): Collation
    {
        return $this->schemas[strtolower($name)] ?? $this->server ?? $this->connection;
    }
}
