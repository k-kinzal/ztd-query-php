<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

use SqlSemantics\Model\Expression;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A typed JSON_TABLE path existence test column.
 * @visibility public
 * @example Reading an existence test column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (ok BOOLEAN EXISTS PATH '$.a' TRUE ON ERROR)) AS j");
 *     $statement->from->table->columns[0]->onError // => \SqlSemantics\Model\TableFunction\Json\Response\ExistsResponse::True
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
