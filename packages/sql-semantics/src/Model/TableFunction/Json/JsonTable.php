<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A JSON row expansion with explicit paths, output declarations, and error behavior.
 * @visibility public
 */
final class JsonTable
{
    /**
     * SQL language shared by the input document and every declaration.
     */
    public readonly \SqlSemantics\Dialect $dialect;

    /**
     * @var non-empty-list<Column> Ordered output declarations
     */
    public readonly array $columns;

    /**
     * @param list<Column> $columns
     * @param list<PassingArgument> $passing
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly Input $document,
        public readonly Expression $path,
        array $columns,
        public readonly ?string $pathName = null,
        public readonly array $passing = [],
        public readonly Response\TableError $onError = Response\TableError::Default,
    ) {
        Collections::objects($columns, Column::class);
        Collections::objects($passing, PassingArgument::class);
        if ($columns === []) {
            throw new InvalidStructure('JSON_TABLE requires output column declarations.');
        }
        $this->columns = $columns;
        $this->dialect = $document->expression->type->dialect;
        \SqlSemantics\Model\TableFunction\DocumentInvariant::check($this);
    }
}
