<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Contract;

/**
 * Exact shipped grammar and lexical artifacts, pinned independently of a mutable parser.
 *
 * Digests identify artifacts, not semantic completeness. Changing a shipped artifact
 * requires a deliberate profile revision and review of its semantic rules.
 * @visibility public
 * @example Selecting a fixed grammar release
 *     \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172->value // => 'pg-17.2'
 */
enum GrammarRelease: string
{
    case PostgreSql166 = 'pg-16.6';
    case PostgreSql172 = 'pg-17.2';

    /**
     * Identifies the exact compiled grammar artifact.
     */
    public function grammarDigest(): string
    {
        return match ($this) {
            self::PostgreSql166 => '7d55fbcd39febf445fd8d8a4a7884486f1a30d6e62ab1a10bea346ae27c8af90',
            self::PostgreSql172 => '82294937323322564a269aa095eaced2a64b5f2b9bf547d32b273bef6eec15b6',
        };
    }

    /**
     * Identifies the exact keyword artifact used for lexical interpretation.
     */
    public function keywordDigest(): string
    {
        return match ($this) {
            self::PostgreSql166 => '30153c013e398cc0385ae9ce741c15f7436a53b3fc3bb2eee0b0bcc388efde49',
            self::PostgreSql172 => '6f474037d9223516399697bd44984f2ebe9eea536e037e3876c372c32e775ceb',
        };
    }

    /**
     * Names the database whose lexical and semantic rules interpret this artifact.
     */
    public function database(): string
    {
        return 'postgresql';
    }
}
