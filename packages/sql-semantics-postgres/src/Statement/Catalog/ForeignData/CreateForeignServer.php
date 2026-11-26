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
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\GenericOption;

/**
 * A request to define a foreign server.
 *
 * Rule: PG-SERVER-001. Mirrors `CreateForeignServerStmt`: name, type,
 * version, wrapper and options.
 * Source: https://www.postgresql.org/docs/17/sql-createserver.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateForeignServer implements Statement
{
    use Snapshot;

    /**
     * @var list<GenericOption> The options
     */
    public readonly array $options;

    /**
     * @param Name $name The server name
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param StringConstant|null $type The server type, when TYPE is written
     * @param ServerVersion|null $version The version clause, when written
     * @param Name $wrapper The foreign-data wrapper
     * @param list<GenericOption> $options The options
     */
    public function __construct(
        public readonly Name $name,
        public readonly bool $ifNotExists,
        public readonly ?StringConstant $type,
        public readonly ?ServerVersion $version,
        public readonly Name $wrapper,
        array $options = [],
    ) {
        $this->options = Check::listOf($options, GenericOption::class, 'Server options are a list of options.');
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
        $out->keyword('CREATE', 'SERVER');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->name($this->name, NameUse::Column);
        if ($this->type !== null) {
            $out->keyword('TYPE')->node($this->type);
        }
        $out->node($this->version)->keyword('FOREIGN', 'DATA', 'WRAPPER')->name($this->wrapper, NameUse::Column);
        (new ForeignOptions())->write($out, $this->options);
    }
}
