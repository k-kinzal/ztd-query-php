<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance;

use Override;

/**
 * Typed AnalyzeNamedStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
 * @example Analyzing one table
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ANALYZE main.t');
 *     $statement instanceof \SqlSemantics\Model\Statement\Maintenance\AnalyzeNamedStatement // => true
 *     $statement->target->parts // => ['main', 't']
 */
final class AnalyzeNamedStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     * @visibility SqlSemantics
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
        public readonly \SqlSemantics\Model\Relation\QualifiedName $target,
    ) {
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): \SqlSemantics\Model\Statement\StatementKind
    {
        return \SqlSemantics\Model\Statement\StatementKind::Analyze;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(\SqlSemantics\Model\Statement\Origin $origin): static
    {
        return new static($origin, $this->target);
    }



}
