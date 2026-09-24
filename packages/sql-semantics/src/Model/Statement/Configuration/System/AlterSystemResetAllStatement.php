<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\System;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes every parameter from the PostgreSQL automatic configuration file.
 * @visibility public
 * @example Recognizing the full reset
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SYSTEM RESET ALL');
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\System\AlterSystemResetAllStatement // => true
 * @example Rejecting another dialect
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Configuration\System\AlterSystemResetAllStatement($origin); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterSystemResetAllStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('ALTER SYSTEM requires PostgreSQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the full reset while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin);
    }
}
