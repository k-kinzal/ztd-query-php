<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Storage;

/**
 * Requests whether the pages of a tablespace are encrypted.
 * @visibility public
 * @example Naming the encrypted request
 *     \SqlSemantics\Model\Definition\Storage\StorageEncryption::Enabled->value // => 'Y'
 */
enum StorageEncryption: string
{
    case Enabled = 'Y';
    case Disabled = 'N';
}
