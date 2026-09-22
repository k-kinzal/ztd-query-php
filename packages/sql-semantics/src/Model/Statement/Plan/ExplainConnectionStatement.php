<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Plan;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Plan\MySqlFormat;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;

/**
 * Requests the currently executing plan of a MySQL connection.
 * @visibility public
 */
final class ExplainConnectionStatement extends BoundStatement
{
    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly NumericParameter $connection, public readonly MySqlFormat $format = MySqlFormat::Default)
    {
        parent::__construct($origin);
        if ($origin->dialect !== \SqlSemantics\Dialect::MySql || preg_match('/^[0-9]+$/D', $connection->spelling) !== 1) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A connection plan requires a MySQL connection number.');
        }
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Explain;
    }

    /**
     * Reconstructs the same operation with replacement diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->connection, $this->format);
    }
}
