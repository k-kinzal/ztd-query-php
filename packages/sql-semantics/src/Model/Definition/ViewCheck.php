<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

/**
 * ViewCheck alternatives.
 *
 * @visibility public
 * @example Reading the check option
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE VIEW v AS SELECT 1 WITH LOCAL CHECK OPTION');
 *     $statement->check // => \SqlSemantics\Model\Definition\ViewCheck::Local
 */
enum ViewCheck: string
{
    case None = '';
    case Local = 'LOCAL';
    case Cascaded = 'CASCADED';
}
