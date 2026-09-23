<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\MySql;

/**
 * The semantic role of one table-maintenance status result.
 * @visibility public
 * @example Reading the request domain
 *     \SqlSemantics\Model\Maintenance\MySql\StatusField::Table->value // => 'Table'
 */
enum StatusField: string
{
    case Table = 'Table';
    case Operation = 'Op';
    case MessageType = 'Msg_type';
    case Message = 'Msg_text';
}
