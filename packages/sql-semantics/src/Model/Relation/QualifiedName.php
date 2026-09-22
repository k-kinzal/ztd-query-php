<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

/**

 * An ordered identifier path, never an SQL fragment. @visibility public

 */
final class QualifiedName
{
    /**
     * @var non-empty-list<string>
     */
    public readonly array $parts;

    /**

     * @param list<string> $parts

     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(array $parts)
    {
        \SqlSemantics\Model\Validation\Collections::strings($parts);
        if ($parts === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An identifier requires nonempty name parts.');
        }
        $this->parts = $parts;
    }
}
