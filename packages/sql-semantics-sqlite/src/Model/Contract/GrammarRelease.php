<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Contract;

/**
 * Exact shipped grammar and lexical artifacts, pinned independently of a mutable parser.
 *
 * Digests identify artifacts, not semantic completeness. Changing a shipped artifact
 * requires a deliberate profile revision and review of its semantic rules.
 * @visibility public
 * @example Selecting a fixed grammar release
 *     \SqlSemantics\Platform\Sqlite\Model\Contract\GrammarRelease::Sqlite3472->value // => 'sqlite-3.47.2'
 */
enum GrammarRelease: string
{
    case Sqlite3472 = 'sqlite-3.47.2';

    /**
     * Identifies the exact compiled grammar artifact.
     */
    public function grammarDigest(): string
    {
        return match ($this) {
            self::Sqlite3472 => '579e2b88d7ca99a261b9faeae3401a2344850b91ad3c276b72ab51e608633884',
        };
    }

    /**
     * Identifies the exact keyword artifact used for lexical interpretation.
     */
    public function keywordDigest(): string
    {
        return match ($this) {
            self::Sqlite3472 => '8276cc16f199de0461eafc245833b8a1808f17a133ea6587bfef9f2d18f69b90',
        };
    }

    /**
     * Names the database whose lexical and semantic rules interpret this artifact.
     */
    public function database(): string
    {
        return 'sqlite';
    }
}
