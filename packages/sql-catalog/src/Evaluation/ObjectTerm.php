<?php

declare(strict_types=1);

namespace SqlCatalog\Evaluation;

use Override;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

/**
 * An object the analyzer tracked by class.
 *
 * Two kinds of object matter to the catalog. An enum case carries the case name,
 * which resolves `->value` to a literal. A prepared statement carries the
 * identifier of the catalog entry it came from, which is how a later
 * `execute()` finds the query it binds values to.
 *
 * @visibility root
 */
final class ObjectTerm implements Term
{
    /**
     * @param string $className The fully qualified class name, without a leading backslash
     * @param string|null $enumCase The case name when the object is an enum case
     * @param string|null $statementId The catalog entry a prepared statement belongs to
     */
    public function __construct(
        public readonly string $className,
        public readonly ?string $enumCase = null,
        public readonly ?string $statementId = null,
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
        return 'object:' . $this->className . ':' . ($this->enumCase ?? '') . ':' . ($this->statementId ?? '');
    }
}
