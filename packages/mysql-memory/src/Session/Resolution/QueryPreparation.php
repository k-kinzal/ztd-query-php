<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Resolution;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Statement\Query;

/**
 * A bound stored query submitted to the ordinary preparation diagnostics.
 *
 * Retains the stored query's local meaning and the invocation's preparation
 * conditions, including existence-only reads and deferred write operands.
 *
 * @visibility MySqlMemory
 */
final class QueryPreparation
{
    /**
     * @param Query $statement The complete stored query being prepared
     * @param StatementSemantics $facts The query's facts in the current invocation context
     * @param LanguageProfile $language The grammar and lexical interpretation
     * @param bool $exists Whether this query is prepared only as an existence test
     * @param bool $deferSetWidth Whether the surrounding legacy predicate binds its operand before fixed set widths
     * @param \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery|null $write The complete write owning this read, after its destination opens
     */
    public function __construct(public readonly Query $statement, public readonly StatementSemantics $facts, public readonly LanguageProfile $language, public readonly bool $exists = false, public readonly bool $deferSetWidth = false, public readonly ?\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery $write = null)
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
