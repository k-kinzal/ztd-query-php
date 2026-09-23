<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Foreign;

/**
 * Requests every table exposed by the remote schema.
 * @visibility public
 * @example Selecting the whole remote schema
 *     \SqlSemantics\Model\Definition\Foreign\AllForeignTables::InSchema->value // => 'all'
 */
enum AllForeignTables: string
{
    case InSchema = 'all';
}
