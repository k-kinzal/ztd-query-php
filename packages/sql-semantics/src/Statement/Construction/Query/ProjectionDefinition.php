<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Query;

/**
 * The full ordered projection requested for a new query.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Query\ProjectionDefinition(new \SqlSemantics\Statement\Construction\Query\FieldDefinition(new \SqlSemantics\Statement\Expression\NullConstant()), new \SqlSemantics\Statement\Construction\Query\FieldDefinition(new \SqlSemantics\Statement\Expression\NullConstant()));
 *     count($input->fields) // => 2
 */
final class ProjectionDefinition
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var non-empty-list<FieldDefinition>
     */
    public readonly array $fields;

    /**
     * Keeps the complete supplied sequence, including duplicate values.
     */
    public function __construct(FieldDefinition $first, FieldDefinition ...$rest)
    {
        $this->fields = [$first, ...array_values($rest)];
    }
}
