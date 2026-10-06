<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyArguments;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyFlag;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyForce;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyOption;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyText;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
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
 * is reported. A column a FORCE option names, in the old syntax or as the
 * list argument of `force_quote`, `force_not_null` or `force_null`, that is a
 * generated column is reported (`column "b" is a generated column`,
 * CopyGetAttnums, copy.c); a column a FORCE option of the old syntax names
 * that is not copied is reported when the copied columns are known.
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
     * @param list<Name> $generated The generated columns of the table
     */
    public function check(bool $binary, bool $delimiters, array $legacy, array $options, ?array $copied, Derivation $derivation, array $generated = []): void
    {
        $names = $binary ? ['format'] : [];
        if ($delimiters) {
            $names[] = 'delimiter';
        }
        foreach ($legacy as $item) {
            $names[] = $item->option();
            if ($item instanceof CopyForce) {
                $this->copied(strtoupper($item->option()), $item->columns, $copied, $generated, $derivation);
            }
        }
        foreach ($options as $option) {
            $forced = in_array($option->name->value, ['force_quote', 'force_not_null', 'force_null'], true) ? $this->listed($option) : [];
            $this->copied(strtoupper($option->name->value), $forced, null, $generated, $derivation);
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
     * Answers the column names the list argument of an option holds, or none when its argument is not a list.
     *
     * @return list<Name>
     */
    public function listed(CopyOption $option): array
    {
        $names = [];
        foreach ($option->argument instanceof CopyArguments ? $option->argument->items : [] as $item) {
            if ($item instanceof Word) {
                $names[] = $item->word;
            } elseif ($item instanceof StringConstant) {
                $names[] = new Name($item->value);
            }
        }

        return $names;
    }

    /**
     * Reports the columns of a FORCE option that are generated columns or, when the copied columns are known, are not copied.
     *
     * @param string $option The option name as the server spells it in its messages
     * @param list<Name> $columns The columns the option names
     * @param list<Name>|null $copied The columns copied, or null when they are not known
     * @param list<Name> $generated The generated columns of the table
     */
    public function copied(string $option, array $columns, ?array $copied, array $generated, Derivation $derivation): void
    {
        $names = $derivation->context->columnNames;
        foreach ($columns as $column) {
            $match = static fn (Name $name): bool => $names->equal($name->value, $column->value);
            if (array_filter($generated, $match) !== []) {
                $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::CopyGeneratedColumn, $column->value));
            } elseif ($copied !== null && array_filter($copied, $match) === []) {
                $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::CopyColumnNotCopied, $option, $column->value));
            }
        }
    }
}
