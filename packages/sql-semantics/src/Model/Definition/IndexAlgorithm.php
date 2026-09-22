<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

/**
 * Requested algorithm for a MySQL index operation.
 * @visibility public
 */
enum IndexAlgorithm: string
{
    case Default = 'DEFAULT';
    case Inplace = 'INPLACE';
    case Copy = 'COPY';
}
