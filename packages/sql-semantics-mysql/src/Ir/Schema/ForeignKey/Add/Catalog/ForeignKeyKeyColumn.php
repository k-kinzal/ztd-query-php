<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A full column in the leading native prefix of a physical key.
 * @visibility public
 * @example Identifying a full ascending key column
 *     $field = new \SqlSemantics\Platform\MySql\Ir\Identifier('owner_id');
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyKeyColumn($field))->descending // => false
 */
final class ForeignKeyKeyColumn
{
    use Snapshot;

    /**
     * Checks a physical field coordinate and fixes its direction.
     */
    public function __construct(public readonly Identifier $field, public readonly bool $descending = false)
    {
        Check::input(preg_match('/\A.{1,64}(?<! )\z/us', $field->value) === 1, 'A native key prefix requires a physical field name.');
    }
}
