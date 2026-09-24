<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Session;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW ERRORS and SHOW WARNINGS.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Session\DiagnosticField::Level->label() // => 'Level'
 */
enum DiagnosticField: string implements MetadataField
{
    use TextField;

    case Level = 'Level';
    case Code = 'Code';
    case Message = 'Message';

    /**
     * The condition code is an integer.
     */
    public function type(): string
    {
        return $this === self::Code ? 'integer' : 'varchar';
    }
}
