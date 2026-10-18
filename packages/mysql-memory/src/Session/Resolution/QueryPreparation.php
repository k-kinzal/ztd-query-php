<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Resolution;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Query;

/**
 * A bound stored query submitted to the ordinary preparation diagnostics.
 *
 * This consumer input is not a published semantic Operation. The original
 * Query and its derived facts remain sufficient; no invalid QueryRoot is made.
 *
 * @visibility MySqlMemory
 */
final class QueryPreparation
{
    /**
     * @param Query $statement The complete stored query being prepared
     * @param Facts $facts The query's facts in the current invocation context
     * @param LanguageProfile $language The grammar and lexical interpretation
     * @param bool $exists Whether this query is prepared only as an existence test
     * @param bool $deferSetWidth Whether the surrounding legacy predicate binds its operand before fixed set widths
     */
    public function __construct(public readonly Query $statement, public readonly Facts $facts, public readonly LanguageProfile $language, public readonly bool $exists = false, public readonly bool $deferSetWidth = false)
    {
    }

    /**
     * Answers the interpretation used to derive these facts.
     */
    public function profile(): LanguageProfile
    {
        return $this->language;
    }
}
