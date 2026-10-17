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
     *
     * @throws \SqlSemantics\Diagnostic\UnrepresentableName When the output character set cannot represent the name
     */
    public function name(Name $name, NameUse $use): string;

    /**
     * Encodes an already quoted or bare identifier spelling from the model's
     * name representation to the output character set, preserving its quoting.
     *
     * @throws \SqlSemantics\Diagnostic\UnrepresentableName When the output character set cannot represent the name
     */
    public function spelling(string $text): string;
}
