<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance;

use Override;

/**
 * Typed AnalyzeAllStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
 * @example Analyzing every table
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build()))->bind('ANALYZE');
 *     $statement instanceof \SqlSemantics\Model\Statement\Maintenance\AnalyzeAllStatement // => true
 *     $statement->toString() // => 'ANALYZE'
 */
final class AnalyzeAllStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     * @visibility SqlSemantics
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
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
        return new static($origin, );
    }



}
