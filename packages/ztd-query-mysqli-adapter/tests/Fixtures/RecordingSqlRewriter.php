<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Closure;
use LogicException;
use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Sql\TransactionStatement;

/**
 * A rewriter that answers from closures and remembers every statement it was given.
 *
 * A test that needs to know which statements were split and which were rewritten
 * can read them off afterwards. Saying the same thing as mock expectations would
 * need the mock sealed, which the older PHPUnit majors this package supports
 * cannot do.
 */
final class RecordingSqlRewriter implements SqlRewriter
{
    /**
     * Statements handed to splitStatements(), in order.
     *
     * @var list<string>
     */
    public array $split = [];

    /**
     * Statements handed to rewrite(), in order.
     *
     * @var list<string>
     */
    public array $rewritten = [];

    /**
     * Binds the rewriter to what it answers.
     *
     * @param Closure(string): list<string> $splitAnswer How a batch is split
     * @param Closure(string): RewritePlan $rewriteAnswer What a statement is rewritten to
     */
    public function __construct(
        private readonly Closure $splitAnswer,
        private readonly Closure $rewriteAnswer,
    ) {
    }

    /**
     * Answers that nothing here is a transaction statement.
     *
     * @param string $sql Ignored
     *
     * @return TransactionStatement|null Always null
     */
    public function transactionStatement(string $sql): ?TransactionStatement
    {
        return null;
    }

    /**
     * Answers the select that stands for no rows.
     *
     * @return string The statement
     */
    public function emptyResultSelect(): string
    {
        return 'SELECT 1 WHERE 1 = 0';
    }

    /**
     * Splits a batch, and remembers being asked to.
     *
     * @param string $sql Batch as it was written
     *
     * @return list<string> The statements
     */
    public function splitStatements(string $sql): array
    {
        $this->split[] = $sql;

        return ($this->splitAnswer)($sql);
    }

    /**
     * Rewrites a statement, and remembers being asked to.
     *
     * @param string $sql Statement as it was written
     *
     * @return RewritePlan What to carry out instead
     */
    public function rewrite(string $sql): RewritePlan
    {
        $this->rewritten[] = $sql;

        return ($this->rewriteAnswer)($sql);
    }

    /**
     * Refuses, because no test here rewrites a batch in one call.
     *
     * @param string $sql Ignored
     *
     * @return MultiRewritePlan Never answered
     *
     * @throws LogicException Always
     */
    public function rewriteMultiple(string $sql): MultiRewritePlan
    {
        throw new LogicException('This rewriter answers one statement at a time.');
    }
}
