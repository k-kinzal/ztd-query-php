<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json\Response;

/**
 * A classified JSON_TABLE response for this operation.
 * @visibility public
 */
enum ExistsResponse: string
{
    case Default = '';
    case Error = 'ERROR';
    case True = 'TRUE';
    case False = 'FALSE';
    case Unknown = 'UNKNOWN';
}
