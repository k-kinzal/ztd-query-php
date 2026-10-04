<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyFlag;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyForce;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyOption;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyText;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Checks the options of COPY.
 *
 * Rule: PG-COPY-OPTIONS-001. Every option of the old syntax, BINARY after
 * COPY and DELIMITERS set a generic option (`format`, `freeze`,
 * `delimiter`, `null`, `header`, `quote`, `escape`, `encoding`,
 * `force_quote`, `force_not_null`, `force_null`). A parenthesized option
 * must be one PostgreSQL knows: those, `default` and `convert_selectively`,
 * and from PostgreSQL 17 `on_error` and `log_verbosity`; the names compare
 * exactly, as written after folding. An option set twice, by any spelling,
 * is reported. A FORCE option of the old syntax naming a column that is not
 * copied is reported when the copied columns are known.
 * Termination: one pass over the options.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html, https://www.postgresql.org/docs/16/sql-copy.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CopyOptions
{
    /**
     * The option names every release knows.
     */
    private const KNOWN = [
        'format', 'freeze', 'delimiter', 'null', 'default', 'header', 'quote', 'escape', 'force_quote', 'force_not_null', 'force_null',
        'convert_selectively', 'encoding',
    ];

    /**
     * The option names PostgreSQL 17 adds.
     */
    private const ADDED = ['on_error', 'log_verbosity'];

    /**
     * Checks the options of a COPY statement.
     *
     * @param list<CopyFlag|CopyText|CopyForce> $legacy
     * @param list<CopyOption> $options
     * @param list<Name>|null $copied The columns copied, or null when they are not known
     */
    public function check(bool $binary, bool $delimiters, array $legacy, array $options, ?array $copied, Derivation $derivation): void
    {
        $names = $binary ? ['format'] : [];
        if ($delimiters) {
            $names[] = 'delimiter';
        }
        foreach ($legacy as $item) {
            $names[] = $item->option();
            if ($item instanceof CopyForce && $copied !== null) {
                $this->copied($item, $copied, $derivation);
            }
        }
        $known = $derivation->context->profile->grammar === GrammarRelease::PostgreSql166 ? self::KNOWN : [...self::KNOWN, ...self::ADDED];
        foreach ($options as $option) {
            if (!in_array($option->name->value, $known, true)) {
                $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::CopyUnknownOption, $option->name->value));
                continue;
            }
            $names[] = $option->name->value;
        }
        foreach (array_count_values($names) as $count) {
            for (; $count > 1; $count--) {
                $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::CopyRedundantOption));
            }
        }
    }

    /**
     * Reports the columns of a FORCE option that are not copied.
     *
     * @param list<Name> $copied
     */
    public function copied(CopyForce $force, array $copied, Derivation $derivation): void
    {
        foreach ($force->columns as $column) {
            $found = false;
            foreach ($copied as $name) {
                $found = $found || $derivation->context->columnNames->equal($name->value, $column->value);
            }
            if (!$found) {
                $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::CopyColumnNotCopied, strtoupper($force->option()), $column->value));
            }
        }
    }
}
