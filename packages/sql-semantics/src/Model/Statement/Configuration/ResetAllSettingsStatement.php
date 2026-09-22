<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Resets every session configuration parameter to its default.
 * @example Reading the operation's structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('RESET ALL', strict: false);
 *     $statement->toString() // => 'RESET ALL'
 *
 * @visibility public
 */
final class ResetAllSettingsStatement extends ConfigurationStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('This reset operation requires PostgreSql.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Reset;
    }

    /**
     * Retains the reset operation while replacing its diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin);
    }
}
