<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Text;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Converts identifier bytes between a Latin-1 client and the system character set.
 *
 * MySQL latin1 follows Windows-1252, including the five unassigned C1 controls.
 * UTF-8 clients already supply the representation used by decoded names. Other
 * client sets retain their existing byte representation until a conversion rule
 * is available. This conversion belongs to input and rendering, never execution.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class NameEncoding
{
    /**
     * Reads decoded identifier bytes in the client character set.
     */
    public function decode(string $bytes, ?Charset $client): string
    {
        return $client?->name === 'latin1' ? mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252') : $bytes;
    }

    /**
     * Writes a decoded identifier in the client character set.
     */
    public function encode(string $name, ?Charset $client): string
    {
        return $client?->name === 'latin1' ? mb_convert_encoding($name, 'Windows-1252', 'UTF-8') : $name;
    }
}
