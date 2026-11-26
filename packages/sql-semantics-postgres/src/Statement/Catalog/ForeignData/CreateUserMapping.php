<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ForeignOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Option\GenericOption;

/**
 * A request to define how a user connects to a foreign server.
 *
 * Rule: PG-USER-MAPPING-001. Mirrors `CreateUserMappingStmt`. The user is a
 * role specification or the keyword USER, which designates the current user.
 * Source: https://www.postgresql.org/docs/17/sql-createusermapping.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateUserMapping implements Statement
{
    use Snapshot;

    /**
     * @var list<GenericOption> The options
     */
    public readonly array $options;

    /**
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param RoleSpec|MappingUser $user The user
     * @param Name $server The foreign server
     * @param list<GenericOption> $options The options
     */
    public function __construct(public readonly bool $ifNotExists, public readonly RoleSpec|MappingUser $user, public readonly Name $server, array $options = [])
    {
        $this->options = Check::listOf($options, GenericOption::class, 'User mapping options are a list of options.');
    }

    /**
     * Derives nothing: a user mapping is not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'USER', 'MAPPING');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->keyword('FOR')->node($this->user)->keyword('SERVER')->name($this->server, NameUse::Column);
        (new ForeignOptions())->write($out, $this->options);
    }
}
