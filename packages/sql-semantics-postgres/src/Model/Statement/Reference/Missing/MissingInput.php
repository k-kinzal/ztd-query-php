<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing;

/**
 * An input the analysis context does not contain and a fact therefore depends on.
 *
 * @visibility SqlSemantics
 */
interface MissingInput
{
    /**
     * Describes the missing input for a person.
     */
    public function describe(): string;
}
