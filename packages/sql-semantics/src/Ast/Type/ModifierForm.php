<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type;

/**
 * The simple constant forms PostgreSQL accepts as type-modifier inputs.
 * @visibility SqlSemantics
 */
enum ModifierForm
{
    case Number;
    case Text;
    case Identifier;
}
