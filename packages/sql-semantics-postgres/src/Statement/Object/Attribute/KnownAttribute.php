<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute;

/**
 * An attribute name a command recognizes, with the way the command reads its value.
 *
 * Each command compares the attribute names with the names it knows,
 * respecting case; an unquoted name is folded to lower case first. Each
 * command has its own set: the cases of the enum for that command.
 * Source: https://www.postgresql.org/docs/17/sql-createoperator.html, `DefineOperator` in `src/backend/commands/operatorcmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading how an operator reads its function attribute
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute::named('function')?->reading() // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading::Function
 */
interface KnownAttribute
{
    /**
     * Answers the member with exactly this name, or null when the command does not recognize the name.
     */
    public static function named(string $name): ?self;

    /**
     * Answers the attribute name the command compares with.
     */
    public function text(): string;

    /**
     * Answers how the command reads the attribute's value.
     */
    public function reading(): Reading;
}
