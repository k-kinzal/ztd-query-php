<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Session;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW ENGINE ... STATUS, LOGS, and MUTEX.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Session\EngineReportField::Type->label() // => 'Type'
 */
enum EngineReportField: string implements MetadataField
{
    use TextField;

    case Type = 'Type';
    case Name = 'Name';
    case Status = 'Status';
}
