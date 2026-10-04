<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\ForeignData;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ForeignOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to change the options of a user mapping.
 *
 * Rule: PG-USER-MAPPING-002. Mirrors `AlterUserMappingStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-alterusermapping.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a user mapping
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("ALTER USER MAPPING FOR bob SERVER s OPTIONS (SET password 'x')");
 *     $operation->toString() // => "ALTER USER MAPPING FOR bob SERVER s OPTIONS (SET password 'x')"
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
