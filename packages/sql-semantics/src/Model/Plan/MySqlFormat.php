<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Plan;

/**
 * The selected MySqlFormat instruction.
 * @visibility public
 */
enum MySqlFormat: string
{
    case Default = '';
    case Traditional = 'TRADITIONAL';
    case Json = 'JSON';
    case Tree = 'TREE';
}
