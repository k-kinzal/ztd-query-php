<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

/**
 * Spells decoded string bytes under a language profile's lexical settings.
 *
 * The returned spelling must decode to the input bytes. Lexical escape
 * settings belong to the codec, not to an already decoded literal value.
 *
 * @visibility SqlSemantics
 */
interface StringCodec extends Codec
{
    /**
     * Quotes decoded bytes as one string token.
     */
    public function quote(string $value): string;
}
