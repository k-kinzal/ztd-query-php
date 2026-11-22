<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Problem;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A query that PostgreSQL rejects because it breaks a rule of the language.
 *
 * @visibility SqlSemantics
 */
final class QueryMisuse implements Diagnostic
{
    use Snapshot;

    /**
     * @param QueryMisuseRule $rule The broken rule
     * @param Name|null $subject The name the problem is about, for the rules that name one
     */
    public function __construct(public readonly QueryMisuseRule $rule, public readonly ?Name $subject = null)
    {
    }

    /**
     * Describes the broken rule in the words of PostgreSQL.
     */
    public function message(): string
    {
        return str_replace('%s', $this->subject->value ?? '', $this->rule->value);
    }
}
