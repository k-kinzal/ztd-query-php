<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * Finds where a run of keywords was written, at the level of the statement itself.
 *
 * A clause is opened by words that only mean what they say when the statement
 * itself says them: an ORDER BY inside a subquery orders the subquery, not the
 * statement around it. Only a run written entirely outside parentheses counts.
 */
final class SqlKeywordSequence
{
    /**
     * Answers where a run of keywords begins.
     *
     * @param list<SqlToken> $tokens Tokens to search, with nothing insignificant left in
     * @param non-empty-list<string> $keywords Words the run is made of, in order
     * @param int $from Position to start looking from
     *
     * @return int|null Position of the run's first word, or null when it is not written there
     */
    public function positionIn(array $tokens, array $keywords, int $from): ?int
    {
        $limit = count($tokens) - count($keywords);
        for ($index = $from; $index <= $limit; $index++) {
            $token = $tokens[$index];
            if (!$token->isTopLevel()) {
                continue;
            }
            foreach ($keywords as $relative => $keyword) {
                $candidate = $tokens[$index + $relative];
                if (!$candidate->isTopLevel() || !$candidate->isKeyword($keyword)) {
                    continue 2;
                }
            }

            return $index;
        }

        return null;
    }
}
