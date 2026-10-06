<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

/**
 * The change of a parent row a referential action answers: ON UPDATE or ON DELETE.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html#foreign-key-referential-actions.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceEvent::Delete->value // => 'DELETE'
 */
enum ReferenceEvent: string
{
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
