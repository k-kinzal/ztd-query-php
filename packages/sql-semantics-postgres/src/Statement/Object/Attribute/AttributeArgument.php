<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute;

use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * The value of a recognized definition attribute, read the way its command reads it.
 *
 * Each class is one way of reading: an object name, a type name, a Boolean,
 * an integer, a type length, text, or one of a fixed set of words. The
 * written value is kept so the attribute is written back as it was given.
 * Source: `src/backend/commands/define.c` of PostgreSQL 17.
 *
 * @visibility SqlSemantics
 */
interface AttributeArgument extends Clause
{
    /**
     * Tells whether the value is what the reading yields.
     */
    public function fits(Reading $reading): bool;
}
