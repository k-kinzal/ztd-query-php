<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Sampling;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A TABLESAMPLE clause on one table occurrence: the sampling method, its arguments and, in PostgreSQL, the optional
 * REPEATABLE seed. A PostgreSQL extension method (such as tsm_system_rows) is a qualified function name.
 *
 * @visibility public
 * @example Reading a PostgreSQL table sample
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)'));
 *     $sample = $binder->bind('SELECT a FROM t TABLESAMPLE BERNOULLI (10) REPEATABLE (1)')->from->sample;
 *     $sample->method // => \SqlSemantics\Model\Query\Sampling\SamplingMethod::Bernoulli
 *     $sample->arguments[0]->structure()->toString() // => '10'
 *     $sample->repeatable?->structure()->toString() // => '1'
 * @example Rejecting a sample without arguments
 *     new \SqlSemantics\Model\Query\Sampling\TableSample(\SqlSemantics\Model\Query\Sampling\SamplingMethod::System, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class TableSample
{
    /**
     * @var non-empty-list<Expression> Method arguments in written order
     */
    public readonly array $arguments;

    /**
     * @param list<Expression> $arguments Method arguments in written order; a built-in method takes exactly one percentage
     * @param Expression|null $repeatable Seed that makes the sample repeatable
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly SamplingMethod|QualifiedName $method,
        array $arguments,
        public readonly ?Expression $repeatable = null,
    ) {
        Collections::objects($arguments, Expression::class);
        $this->arguments = Collections::nonEmpty($arguments);
        if ($method instanceof SamplingMethod && count($arguments) !== 1) {
            throw new InvalidStructure('A built-in sampling method takes exactly one percentage.');
        }
    }
}
