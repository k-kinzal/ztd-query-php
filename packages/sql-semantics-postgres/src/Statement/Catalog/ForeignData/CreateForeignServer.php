<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ForeignOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\GenericOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * A request to define a foreign server.
 *
 * Rule: PG-SERVER-001. Mirrors `CreateForeignServerStmt`: name, type,
 * version, wrapper and options.
 * Source: https://www.postgresql.org/docs/17/sql-createserver.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the wrapper of a new server
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("CREATE SERVER IF NOT EXISTS s TYPE 'pg' VERSION '17' FOREIGN DATA WRAPPER postgres_fdw OPTIONS (host 'db')");
 *     [$operation->wrapper->value, $operation->version?->version?->value] // => ['postgres_fdw', '17']
 */
final class CreateForeignServer extends \SqlSemantics\Statement\SqlStatement implements Statement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

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
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
        ?\SqlSemantics\Statement\StatementSemantics $statementInformation = null
    ) {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
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
