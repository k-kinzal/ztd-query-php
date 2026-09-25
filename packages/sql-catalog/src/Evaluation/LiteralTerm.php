<?php

declare(strict_types=1);

namespace SqlCatalog\Evaluation;

use Override;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

/**
 * A scalar the analyzer resolved exactly.
 *
 * @visibility root
 */
final class LiteralTerm implements Term
{
    private ?string $signature = null;

    /**
     * @param string|int|float|bool|null $value The resolved value
     */
    public function __construct(public readonly string|int|float|bool|null $value)
    {
    }

    /**
     * The value as PHP writes it into a string.
     */
    #[Override]
    public function toPattern(): TextPattern
    {
        return TextPattern::fromText($this->toText());
    }

    /**
     * The value cast to a string the way PHP casts it.
     */
    public function toText(): string
    {
        if ($this->value === null) {
            return '';
        }
        if ($this->value === true) {
            return '1';
        }
        if ($this->value === false) {
            return '';
        }

        return (string) $this->value;
    }

    /**
     * The scalar type of the resolved value.
     */
    #[Override]
    public function type(): TypeShape
    {
        if ($this->value === null) {
            return TypeShape::of(['null']);
        }
        if (is_bool($this->value)) {
            return TypeShape::of(['bool']);
        }
        if (is_int($this->value)) {
            return TypeShape::of(['int']);
        }
        if (is_float($this->value)) {
            return TypeShape::of(['float']);
        }

        return TypeShape::of(['string']);
    }

    /**
     * The value tagged with its type so `1` and `'1'` stay distinct.
     */
    #[Override]
    public function signature(): string
    {
        return $this->signature ??= 'literal:' . get_debug_type($this->value) . ':' . $this->toText();
    }
}
