<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

use SqlSemantics\Model\Expression;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A typed JSON_TABLE value extraction column.
 * @visibility public
 */
final class ValueColumn implements Column
{
    /**
     * A missing path requests the database's implicit path for the declared column name.
     */
    public function __construct(
        public readonly string $name,
        public readonly TypeDescriptor $type,
        public readonly ?Expression $path = null,
        public readonly ?\SqlSemantics\Model\Relation\QualifiedName $collation = null,
        public readonly ?Format $format = null,
        public readonly ArrayWrapping $wrapper = ArrayWrapping::Default,
        public readonly Quotes $quotes = Quotes::Default,
        public readonly Response\ValueResponse $onEmpty = Response\ValueBehavior::Default,
        public readonly Response\ValueResponse $onError = Response\ValueBehavior::Default,
    ) {
    }
}
