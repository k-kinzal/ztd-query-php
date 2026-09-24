<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection;

/**
 * The replication vocabulary a request was written in; it selects the result labels the server returns.
 * @visibility public
 * @example Rewriting a result label into the legacy vocabulary
 *     \SqlSemantics\Model\Query\Inspection\ReplicationVocabulary::Legacy->label('Source_Host') // => 'Master_Host'
 */
enum ReplicationVocabulary: string
{
    case Current = 'current';
    case Legacy = 'legacy';

    /**
     * Rewrites the underscore-separated replication terms of a current-vocabulary label into this vocabulary.
     */
    public function label(string $current): string
    {
        if ($this === self::Current) {
            return $current;
        }
        $terms = ['Source' => 'Master', 'source' => 'master', 'Replica' => 'Slave', 'replica' => 'slave'];
        return implode('_', array_map(static fn (string $part): string => $terms[$part] ?? $part, explode('_', $current)));
    }

    /**
     * Whether a grammar release spells replication requests in this vocabulary: REPLICA from 8.0, SLAVE and MASTER before 8.4.
     */
    public function spelledIn(?string $grammarVersion): bool
    {
        $legacy = ['mysql-5.6.51', 'mysql-5.7.44'];
        $current = ['mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'];
        return !in_array($grammarVersion, $this === self::Current ? $legacy : $current, true);
    }
}
