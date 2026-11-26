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
use SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption;

/**
 * A request to change the options of a user mapping.
 *
 * Rule: PG-USER-MAPPING-002. Mirrors `AlterUserMappingStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-alterusermapping.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterUserMapping implements Statement
{
    use Snapshot;

    /**
     * @var list<AlteredOption> The options
     */
    public readonly array $options;

    /**
     * @param RoleSpec|MappingUser $user The user
     * @param Name $server The foreign server
     * @param list<AlteredOption> $options The options
     */
    public function __construct(public readonly RoleSpec|MappingUser $user, public readonly Name $server, array $options)
    {
        $this->options = Check::listOf($options, AlteredOption::class, 'User mapping options are a list of options.', 1);
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
        $out->keyword('ALTER', 'USER', 'MAPPING');
        $out->keyword('FOR')->node($this->user)->keyword('SERVER')->name($this->server, NameUse::Column);
        (new ForeignOptions())->write($out, $this->options);
    }
}
