<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\OptionChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to install an extension.
 *
 * Rule: PG-EXTENSION-001. Mirrors `CreateExtensionStmt`. The options are
 * kept in the order written; the optional WITH is not kept. An option given
 * twice is a diagnostic, and so is FROM, which the server no longer supports.
 * Source: https://www.postgresql.org/docs/17/sql-createextension.html. Status: Implemented.
 *
 * @visibility public
 * @example Installing an extension into a schema
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE EXTENSION IF NOT EXISTS hstore WITH SCHEMA ext CASCADE');
 *     [$operation->statement->ifNotExists, $operation->toString()] // => [true, 'CREATE EXTENSION IF NOT EXISTS hstore SCHEMA ext CASCADE']
 */
final class CreateExtension implements Statement
{
    use Snapshot;

    /**
     * @var list<ExtensionOption> The options in the order written
     */
    public readonly array $options;

    /**
     * @param Name $name The extension name
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param list<ExtensionOption> $options The options in the order written
     */
    public function __construct(public readonly Name $name, public readonly bool $ifNotExists = false, array $options = [])
    {
        $this->options = Check::listOf($options, ExtensionOption::class, 'Extension options are a list of options.');
    }

    /**
     * Reports repeated options and the unsupported FROM.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $names = [];
        foreach ($this->options as $option) {
            $names[] = $option->option();
        }
        (new OptionChecks())->redundant($derivation, $names);
        if (in_array('old_version', $names, true)) {
            $derivation->report(new CatalogMisuse(CatalogMisuseRule::ExtensionFrom));
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'EXTENSION');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->name($this->name, NameUse::Column);
        foreach ($this->options as $option) {
            $out->node($option);
        }
    }
}
