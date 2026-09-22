<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

use Override;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The declared labels of a MySQL set type.
 * @visibility public
 */
final class LabelSet implements TypeIdentity
{
    /**
     * @var non-empty-list<\SqlSemantics\Model\Scalar\Value\Literal> Validated ordered operands
     */
    public readonly array $labels;

    /**
     * @param list<\SqlSemantics\Model\Scalar\Value\Literal> $labels
     * @throws InvalidStructure
     */
    public function __construct(
        array $labels,
    ) {
        Collections::objects($labels, \SqlSemantics\Model\Scalar\Value\Literal::class);
        foreach ($labels as $label) {
            if ($label->literalKind !== \SqlSemantics\Model\Scalar\Value\LiteralKind::Text || $label->type->dialect !== \SqlSemantics\Dialect::MySql) {
                throw new InvalidStructure('Enumeration labels require MySQL string literals.');
            }
        }
        if ($labels === []) {
            throw new InvalidStructure('A declared label type requires at least one label.');
        }
        $this->labels = Collections::nonEmpty($labels);
    }

    #[Override]
    public function name(): string
    {
        return 'set';
    }
}
