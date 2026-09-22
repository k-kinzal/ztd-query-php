<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json\Response;

/**
 * A classified JSON_TABLE response for this operation.
 * @visibility public
 */
enum TableError: string
{
    case Default = '';
    case Error = 'ERROR';
    case EmptyRows = 'EMPTY';
}
