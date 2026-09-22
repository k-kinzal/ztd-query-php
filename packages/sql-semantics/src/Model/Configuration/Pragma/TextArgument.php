<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Pragma;

/**
 * A classified pragma argument.
 *
 * @visibility public
 */
final class TextArgument implements Argument
{
    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly \SqlSemantics\Model\Scalar\Value\Literal $literal)
    {
        if ($literal->literalKind !== \SqlSemantics\Model\Scalar\Value\LiteralKind::Text) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A pragma text argument requires a text literal.');
        }
    }
}
