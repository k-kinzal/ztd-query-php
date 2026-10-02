<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

use SqlSemantics\Statement\Identifier\Name;

/**
 * Spells decoded names as SQL of one fixed language profile.
 *
 * The spelling must decode to the same name at the given position. A codec
 * does not make arbitrary text safe and does not spell expressions.
 *
 * @visibility SqlSemantics
 */
interface Codec
{
    /**
     * Spells a decoded name for a position, quoting only as the profile requires.
     */
    public function name(Name $name, NameUse $use): string;
}
