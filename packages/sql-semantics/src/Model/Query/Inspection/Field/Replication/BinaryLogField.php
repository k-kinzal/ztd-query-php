<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Replication;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW BINARY LOGS.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Replication\BinaryLogField::Name->label() // => 'Log_name'
 */
enum BinaryLogField: string implements MetadataField
{
    use TextField;

    case Name = 'Log_name';
    case Size = 'File_size';
    case Encrypted = 'Encrypted';

    /**
     * The file size is an integer.
     */
    public function type(): string
    {
        return $this === self::Size ? 'bigint' : 'varchar';
    }
}
