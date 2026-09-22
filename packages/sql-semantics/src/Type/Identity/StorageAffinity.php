<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

/**
 * SQLite column affinity, independent of the runtime storage class.
 * @visibility public
 */
enum StorageAffinity: string
{
    case Integer = 'integer';
    case Text = 'text';
    case Blob = 'blob';
    case Real = 'real';
    case Numeric = 'numeric';
}
