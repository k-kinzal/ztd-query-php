<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

/**
 * The option names the grammar reads as keywords of their own.
 *
 * ANALYZE is a reserved word with the British spelling ANALYSE; both name
 * the option `analyze`. FORMAT directly before the word JSON is read as a
 * keyword of its own and names the option `format`. Every other option name
 * is an ordinary word. The value is the keyword.
 * Source: https://www.postgresql.org/docs/17/sql-explain.html, https://www.postgresql.org/docs/17/sql-analyze.html.
 *
 * @visibility public
 * @example Reading the option both spellings name
 *     [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionKeyword::Analyse->option(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionKeyword::Format->option()] // => ['analyze', 'format']
 */
enum OptionKeyword: string
{
    case Analyze = 'ANALYZE';
    case Analyse = 'ANALYSE';
    case Format = 'FORMAT';

    /**
     * Answers the option name the keyword stands for.
     */
    public function option(): string
    {
        return match ($this) {
            self::Analyze, self::Analyse => 'analyze',
            self::Format => 'format',
        };
    }
}
