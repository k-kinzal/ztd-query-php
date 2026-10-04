<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\OptionChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to update an extension: `ALTER EXTENSION name UPDATE [ TO version ]`.
 *
 * Rule: PG-EXTENSION-002. Mirrors `AlterExtensionStmt`, whose option list
 * holds the TO versions in order; no version updates to the default version,
 * and more than one is a diagnostic.
 * Source: https://www.postgresql.org/docs/17/sql-alterextension.html. Status: Implemented.
 *
 * @visibility public
 * @example Updating to a version
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("ALTER EXTENSION hstore UPDATE TO '2.0'");
 *     $operation->statement->versions[0]->value // => '2.0'
 */
final class UpdateExtension implements Statement
{
    use Snapshot;

    /**
     * @var list<Word|StringConstant> The versions written after TO, in order
     */
    public readonly array $versions;

    /**
     * @param Name $name The extension name
     * @param list<Word|StringConstant> $versions The versions written after TO, in order
     */
    public function __construct(public readonly Name $name, array $versions = [])
    {
        $checked = [];
        foreach (Check::listOf($versions, \SqlSemantics\Statement\Node::class, 'Versions are a list.') as $version) {
            Check::input($version instanceof Word || $version instanceof StringConstant, 'A version is a word or a string.');
            $checked[] = $version;
        }
        $this->versions = $checked;
    }

    /**
     * Reports more than one version.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new OptionChecks())->redundant($derivation, array_fill(0, count($this->versions), 'new_version'));
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'EXTENSION')->name($this->name, NameUse::Column)->keyword('UPDATE');
        foreach ($this->versions as $version) {
            $out->keyword('TO')->node($version);
        }
    }
}
