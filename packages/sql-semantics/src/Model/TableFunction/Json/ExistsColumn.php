<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

use SqlSemantics\Model\Expression;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A typed JSON_TABLE path existence test column.
 * @visibility public
 */
final class ExistsColumn implements Column
{
    /**
     * A missing path requests the database's implicit path for the declared column name.
     */
    public function __construct(
        public readonly string $name,
        public readonly TypeDescriptor $type,
        public readonly ?Expression $path = null,
        public readonly ?\SqlSemantics\Model\Relation\QualifiedName $collation = null,
        public readonly Response\ExistsResponse $onError = Response\ExistsResponse::Default,
    ) {
    }
}
