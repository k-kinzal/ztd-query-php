<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Sql\SqlTokenStream;

/**
 * Table sample parser for PostgreSQL queries.
 */
final class PgSqlTableSampleParser
{
    /**
     * @return list<PgSqlTableSample>
     */
    public function parse(string $sql): array
    {
        $stream = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $samples = [];

        foreach ((new PgSqlSelectRelationParser())->references($sql) as $reference) {
            $referenceToken = (new Parsing\Sampling\SampleTokens())->tokenAtOffset($tokens, $reference['unqualifiedStart']);
            if ($referenceToken === null) {
                continue;
            }
            $sampleIndex = (new Parsing\Sampling\SampleTokens())->sampleIndexAfter($tokens, $referenceToken);
            if ($sampleIndex === null) {
                continue;
            }
            $samples[] = (new Parsing\Sampling\SampleClause())->parseSample($sql, $tokens, $reference, $sampleIndex, $referenceToken);
        }

        return $samples;
    }
}
