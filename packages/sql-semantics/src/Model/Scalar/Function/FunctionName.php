<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**

 * An identifier path naming a function; it is not an expression or SQL fragment. @visibility public

 */
final class FunctionName
{
    /**
     * @var non-empty-list<string>
     */
    public readonly array $parts;

    /**

     * @param list<string> $parts

     * @throws InvalidStructure
     */
    public function __construct(array $parts)
    {
        Collections::strings($parts);
        if ($parts === []) {
            throw new InvalidStructure('An identifier requires nonempty name parts.');
        }
        $this->parts = $parts;
    }
}
