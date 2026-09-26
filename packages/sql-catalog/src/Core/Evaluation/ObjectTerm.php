<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Evaluation;

use Override;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

/**
 * An object the analyzer tracked by class.
 *
 * An enum case carries the case name,
 * which resolves `->value` to a literal. A prepared statement carries the
 * identifier of the catalog entry it came from, which is how a later
 * `execute()` finds the query it binds values to. Modelled builders also carry
 * an allocation identity and immutable state, so aliases observe mutations.
 *
 * @visibility root
 */
final class ObjectTerm implements Term
{
    /**
     * @param string $className The fully qualified class name, without a leading backslash
     * @param string|null $enumCase The case name when the object is an enum case
     * @param string|null $statementId The catalog entry a prepared statement belongs to
     * @param string|null $identity The allocation followed across aliases
     * @param ArrayTerm|null $state The immutable properties of a modelled object
     */
    public function __construct(
        public readonly string $className,
        public readonly ?string $enumCase = null,
        public readonly ?string $statementId = null,
        public readonly ?string $identity = null,
        public readonly ?ArrayTerm $state = null,
    ) {
    }

    /**
     * An object never resolves to text, so it becomes a gap.
     */
    #[Override]
    public function toPattern(): TextPattern
    {
        return TextPattern::fromHole(new TextHole(Origin::Unresolved, $this->type()));
    }

    /**
     * The class of the object.
     */
    #[Override]
    public function type(): TypeShape
    {
        return TypeShape::of([$this->className]);
    }

    /**
     * The class, and the case or statement it stands for.
     */
    #[Override]
    public function signature(): string
    {
        $base = 'object:' . $this->className . ':' . ($this->enumCase ?? '') . ':' . ($this->statementId ?? '');

        return $this->identity === null ? $base : $base . ':' . $this->identity . ':' . ($this->state?->signature() ?? '');
    }
}
