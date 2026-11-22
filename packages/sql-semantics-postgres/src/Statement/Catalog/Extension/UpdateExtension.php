<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\OptionChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;

/**
 * A request to update an extension: `ALTER EXTENSION name UPDATE [ TO version ]`.
 *
 * Rule: PG-EXTENSION-002. Mirrors `AlterExtensionStmt`, whose option list
 * holds the TO versions in order; no version updates to the default version,
 * and more than one is a diagnostic.
 * Source: https://www.postgresql.org/docs/17/sql-alterextension.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
        foreach (Check::listOf($versions, \SqlSemantics\Platform\PostgreSql\Model\Statement\Node::class, 'Versions are a list.') as $version) {
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
