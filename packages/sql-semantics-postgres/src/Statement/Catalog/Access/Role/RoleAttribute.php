<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A role option written as a plain word, such as SUPERUSER, NOCREATEDB or LOGIN.
 *
 * The grammar reads the word as an identifier and the server compares it
 * with its list of role attributes (`RoleFlag`); a word that is not in the
 * list is rejected as an unrecognized role option, which the statement
 * reports. The word is compared as decoded, so a quoted word in another
 * letter case names no attribute.
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html.
 *
 * @visibility public
 * @example Reading the attribute a word names
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE joe CREATEDB');
 *     $operation->statement->options[0]->flag() // => \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleFlag::CreateDb
 */
final class RoleAttribute implements RoleOption
{
    use Snapshot;

    /**
     * @param Name $word The word as decoded
     */
    public function __construct(public readonly Name $word)
    {
    }

    /**
     * Answers the role attribute the word names, or null when the server does not recognize the word.
     */
    public function flag(): ?RoleFlag
    {
        return RoleFlag::tryFrom($this->word->value);
    }

    /**
     * Answers the option the word fills, or null when the word is not recognized.
     */
    public function option(): ?string
    {
        return $this->flag()?->option();
    }

    /**
     * Writes the word as an identifier.
     */
    public function render(Output $out): void
    {
        $out->name($this->word, NameUse::Identifier);
    }
}
