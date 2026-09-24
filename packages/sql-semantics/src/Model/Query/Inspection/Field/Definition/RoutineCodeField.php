<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Definition;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW FUNCTION CODE and SHOW PROCEDURE CODE.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Definition\RoutineCodeField::Position->label() // => 'Pos'
 */
enum RoutineCodeField: string implements MetadataField
{
    use TextField;

    case Position = 'Pos';
    case Instruction = 'Instruction';

    /**
     * The instruction position is an integer.
     */
    public function type(): string
    {
        return $this === self::Position ? 'bigint' : 'varchar';
    }
}
