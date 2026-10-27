<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Semantics;

use SqlSemantics\Statement\AnalyzedStatement;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Source\Span;
use SqlSemantics\Statement\TableUse;

/**
 * Location and binding state of a concrete SQL occurrence.
 * Declaration values and other non-occurrence objects have neither property.
 *
 * @visibility SqlSemantics
 */
trait Occurrence
{
    use Snapshot;

    /**
     * Returns the optional input range owned by this occurrence.
     */
    public function source(): ?Span
    {
        $source = get_object_vars($this)['inputSource'] ?? null;
        return $source instanceof Span ? $source : null;
    }

    /**
     * Reports whether construction resolved this occurrence's semantic role.
     */
    public function hasBinding(): bool
    {
        return ($this instanceof Operand && $this->hasScalarInformation())
            || ($this instanceof TableUse && $this->hasRelationInformation())
            || ($this instanceof Query && $this->hasQueryInformation())
            || ($this instanceof AnalyzedStatement && $this->hasStatementInformation());
    }
}
