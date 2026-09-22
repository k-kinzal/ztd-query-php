<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

/**
 * IdentityMode alternatives.
 *
 * @visibility public
 */
enum IdentityMode: string
{
    case Always = 'always';
    case ByDefault = 'by-default';
}
