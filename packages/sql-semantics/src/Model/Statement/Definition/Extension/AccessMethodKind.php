<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Extension;

/**
 * The kind of relation an access method implements, spelled as in CREATE ACCESS METHOD.
 * @visibility public
 * @example Reading the SQL spelling of a table access method
 *     \SqlSemantics\Model\Statement\Definition\Extension\AccessMethodKind::Table->value // => 'TABLE'
 */
enum AccessMethodKind: string
{
    case Table = 'TABLE';
    case Index = 'INDEX';
}
