<?php

declare(strict_types=1);

namespace SqlCatalog\Evaluation;

use Override;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

/**
 * A value the analyzer knows only by its type and where it came from.
 *
 * @visibility root
 */
final class OpaqueTerm implements Term
{
    /**
     * @param TypeShape $type The static type of the value
     * @param Origin $origin Where the value enters the analyzed code
     * @param string|null $expression The source expression, when short enough to quote
     * @param string|null $variable The PHP variable read at the use site, when known
     */
    public function __construct(
        public readonly TypeShape $type,
        public readonly Origin $origin,
        public readonly ?string $expression = null,
        public readonly ?string $variable = null,
    ) {
    }

    /**
     * A value of unknown type from an unresolved expression.
     */
    public static function unresolved(?string $expression = null): self
    {
        return new self(TypeShape::unknown(), Origin::Unresolved, $expression);
    }

    /**
     * The gap this value leaves in any string it is spliced into.
     */
    #[Override]
    public function toPattern(): TextPattern
    {
        return TextPattern::fromHole(new TextHole($this->origin, $this->type, $this->expression, $this->variable));
    }

    /**
     * The static type recorded for the value.
     */
    #[Override]
    public function type(): TypeShape
    {
        return $this->type;
    }

    /**
     * The type and origin, which is all that distinguishes opaque values.
     */
    #[Override]
    public function signature(): string
    {
        return 'opaque:' . $this->type->display() . ':' . $this->origin->value;
    }
}
