<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use Closure;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClauses;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Statement\Query;

/**
 * Distinguishes standalone VALUES sources from SELECT and set-operation sources.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ValueSources
{
    /**
     * Answers the written VALUES rows without entering row expressions or set operands.
     */
    public function values(Query $source): ?ValuesQuery
    {
        while ($source instanceof QueryExpression || $source instanceof ValuesClauses || $source instanceof QueryStatement || $source instanceof ParenthesizedQuery) {
            $source = $source instanceof QueryExpression || $source instanceof ValuesClauses ? $source->body : $source->query;
        }
        return $source instanceof ValuesQuery ? $source : null;
    }
    /**
     * Derives unused operands while keeping syntax notices and withholding contextualization.
     *
     * @template T
     * @param Closure(): T $derive The retained clause derivation
     * @return T
     */
    public function deferred(\SqlSemantics\Statement\Node $owner, \SqlSemantics\Construction\Derivation $derivation, Closure $derive): mixed
    {
        return $derivation->deferred($owner, $derive, static fn (\SqlSemantics\Statement\Fact\Warning $warning): bool => !$warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation || $warning->phase() !== \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization);
    }

}
