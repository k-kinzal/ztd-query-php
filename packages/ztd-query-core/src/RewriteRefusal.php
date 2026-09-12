<?php

declare(strict_types=1);

namespace ZtdQuery;

use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;

/**
 * What a session makes of a statement the rewriter would not take.
 *
 * A rewriter refuses for two reasons: the statement is written in a way ZTD
 * cannot simulate, or it names a table nothing declared. What should happen
 * then is the caller's to say, and they say it in the configuration — raise
 * it, say something and carry on, or let the statement through untouched —
 * so turning a refusal into a plan is a question of configuration and not of
 * rewriting.
 */
final class RewriteRefusal
{
    /**
     * @param ZtdConfig $config What the caller said should happen
     */
    public function __construct(private readonly ZtdConfig $config)
    {
    }

    /**
     * Answers the plan for a statement ZTD cannot simulate.
     *
     * @param UnsupportedSqlException $refusal Why the rewriter would not take it
     * @param string $sql Statement as it was written
     *
     * @return RewritePlan A plan that does nothing, where the caller allows that
     *
     * @throws DatabaseException When the caller asked to be told by being refused
     */
    public function forUnsupported(UnsupportedSqlException $refusal, string $sql): RewritePlan
    {
        $behavior = $this->config->resolveUnsupportedBehavior($sql);
        if ($behavior === UnsupportedSqlBehavior::Exception) {
            throw new DatabaseException($refusal->getMessage(), null, 0, $refusal);
        }
        if ($behavior === UnsupportedSqlBehavior::Notice) {
            trigger_error(sprintf('[ZTD Notice] Unsupported SQL ignored: %s', $sql), E_USER_NOTICE);
        }

        return new RewritePlan($sql, QueryKind::SKIPPED);
    }

    /**
     * Answers the plan for a statement naming a table nothing declared.
     *
     * @param UnknownSchemaException $refusal Why the rewriter would not take it
     * @param string $sql Statement as it was written
     * @param string $emptyResultSelect Statement that reads nothing back, in the dialect at hand
     *
     * @return RewritePlan A plan that lets the statement through, or one that reads nothing back
     *
     * @throws DatabaseException When the caller asked to be told by being refused
     */
    public function forUnknownSchema(
        UnknownSchemaException $refusal,
        string $sql,
        string $emptyResultSelect,
    ): RewritePlan {
        $behavior = $this->config->unknownSchemaBehavior();
        if ($behavior === UnknownSchemaBehavior::Exception) {
            throw new DatabaseException($refusal->getMessage(), null, 0, $refusal);
        }
        if ($behavior === UnknownSchemaBehavior::Passthrough) {
            return new RewritePlan($sql, QueryKind::READ);
        }
        if ($behavior === UnknownSchemaBehavior::Notice) {
            trigger_error(
                sprintf('[ZTD Notice] Unknown table referenced: %s', $refusal->getIdentifier()),
                E_USER_NOTICE,
            );
        }

        return new RewritePlan($emptyResultSelect, QueryKind::READ);
    }
}
