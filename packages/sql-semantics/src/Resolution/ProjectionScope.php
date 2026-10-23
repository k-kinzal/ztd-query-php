<?php

declare(strict_types=1);

namespace SqlSemantics\Resolution;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;

/**
 * A select list while its expressions are being resolved in order.
 *
 * All declared names are available for diagnosing forward references. A field
 * becomes available only after its expression has been resolved. Dialect rules
 * decide which enclosing positions can refer to this working state.
 *
 * @visibility SqlSemantics
 */
final class ProjectionScope
{
    /** @var array<int, Field> */
    private array $fields = [];

    /**
     * @param array<int, array{Name|MissingInput, Scalar}> $items The declared names and expressions, keyed by select-item position
     */
    public function __construct(public readonly array $items)
    {
    }

    /**
     * Records the completed field of one select item.
     */
    public function bind(int $position, Field $field): void
    {
        $this->fields[$position] = $field;
    }

    /**
     * Answers a completed field, or null while its expression has not been resolved.
     */
    public function field(int $position): ?Field
    {
        return $this->fields[$position] ?? null;
    }
}
