<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

use Override;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The declared labels of a MySQL enum type.
 * @visibility public
 * @example Retaining byte-string labels
 *     $type = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(x ENUM(0x41))')->tables[0]->columns[0]->type;
 *     $type->identity->labels[0]->literalKind->value // => 'binary'
 */
final class Enumeration implements TypeIdentity
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
        public readonly ?string $characterSet = null,
        public readonly bool $binary = false,
    ) {
        Collections::objects($labels, \SqlSemantics\Model\Scalar\Value\Literal::class);
        foreach ($labels as $label) {
            if (!in_array($label->literalKind, [\SqlSemantics\Model\Scalar\Value\LiteralKind::Text, \SqlSemantics\Model\Scalar\Value\LiteralKind::Binary, \SqlSemantics\Model\Scalar\Value\LiteralKind::BitString], true) || $label->type->dialect !== \SqlSemantics\Dialect::MySql) {
                throw new InvalidStructure('Enumeration labels require MySQL text or byte-string literals.');
            }
        }
        if ($labels === []) {
            throw new InvalidStructure('A declared label type requires at least one label.');
        }
        $this->labels = Collections::nonEmpty($labels);
    }

    /**
     * Returns the canonical database type name represented by this identity.
     */
    #[Override]
    public function name(): string
    {
        return 'enum';
    }
}
