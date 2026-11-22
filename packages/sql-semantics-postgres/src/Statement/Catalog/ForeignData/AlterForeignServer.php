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
use SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption;

/**
 * A request to change the version or options of a foreign server.
 *
 * Rule: PG-SERVER-002. Mirrors `AlterForeignServerStmt` with
 * `has_version`. At least a version or an option change is written.
 * Source: https://www.postgresql.org/docs/17/sql-alterserver.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterForeignServer implements Statement
{
    use Snapshot;

    /**
     * @var list<AlteredOption> The option changes
     */
    public readonly array $options;

    /**
     * @param Name $name The server name
     * @param ServerVersion|null $version The version clause, when written
     * @param list<AlteredOption> $options The option changes
     */
    public function __construct(public readonly Name $name, public readonly ?ServerVersion $version, array $options = [])
    {
        $this->options = Check::listOf($options, AlteredOption::class, 'Server option changes are a list of changes.');
        Check::input($version !== null || $this->options !== [], 'ALTER SERVER changes the version or an option.');
    }

    /**
     * Derives nothing: a server is not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'SERVER')->name($this->name, NameUse::Column)->node($this->version);
        (new ForeignOptions())->write($out, $this->options);
    }
}
