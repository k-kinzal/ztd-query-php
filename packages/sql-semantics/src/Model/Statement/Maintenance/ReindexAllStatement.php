<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance;

use Override;

/**
 * Typed ReindexAllStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
 */
final class ReindexAllStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
    ) {
        if ($origin->dialect !== \SqlSemantics\Dialect::Sqlite) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An unqualified REINDEX operation requires SQLite.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): \SqlSemantics\Model\Statement\StatementKind
    {
        return \SqlSemantics\Model\Statement\StatementKind::Reindex;
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
