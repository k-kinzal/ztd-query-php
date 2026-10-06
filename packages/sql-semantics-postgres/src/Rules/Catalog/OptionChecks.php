<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Catalog;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DatabaseOption;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule;

/**
 * Checks the option lists of catalog commands.
 *
 * Rule: PG-CATALOG-OPTION-001. A command that reads its options into one
 * slot each rejects an option given twice ("conflicting or redundant
 * options"). CREATE DATABASE accepts the options the manual lists for the
 * release (`icu_rules` from 16, `builtin_locale` from 17); ALTER DATABASE
 * accepts `allow_connections`, `connection_limit`, `is_template` and
 * `tablespace`, the last only alone. Any other option name is not
 * recognized. Termination: one pass over a finite list.
 * Source: https://www.postgresql.org/docs/17/sql-createdatabase.html,
 * https://www.postgresql.org/docs/16/sql-createdatabase.html, https://www.postgresql.org/docs/17/sql-alterdatabase.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class OptionChecks
{
    /**
     * The options CREATE DATABASE of every supported release recognizes.
     */
    private const CREATE = ['tablespace', 'owner', 'template', 'encoding', 'locale', 'lc_collate', 'lc_ctype', 'icu_locale', 'icu_rules', 'locale_provider', 'collation_version', 'allow_connections', 'connection_limit', 'is_template', 'oid', 'strategy', 'location'];

    /**
     * The options ALTER DATABASE recognizes.
     */
    private const ALTER = ['allow_connections', 'connection_limit', 'is_template', 'tablespace'];

    /**
     * Reports one redundancy when an option name occurs more than once.
     *
     * @param list<string> $names The option names in the order written
     */
    public function redundant(Derivation $derivation, array $names): void
    {
        if (count(array_unique($names)) !== count($names)) {
            $derivation->report(new CatalogMisuse(CatalogMisuseRule::RedundantOptions));
        }
    }

    /**
     * Checks the options of CREATE DATABASE or ALTER DATABASE.
     *
     * @param list<DatabaseOption> $options
     */
    public function database(Derivation $derivation, array $options, bool $alter): void
    {
        $known = $alter ? self::ALTER : self::CREATE;
        if (!$alter && $derivation->context->profile->grammar === GrammarRelease::PostgreSql172) {
            $known[] = 'builtin_locale';
        }
        $names = [];
        foreach ($options as $option) {
            $names[] = $option->option();
            if (!in_array($option->option(), $known, true)) {
                $derivation->report(new CatalogMisuse(CatalogMisuseRule::UnknownOption, [$option->option()]));
            }
        }
        $this->redundant($derivation, $names);
        if ($alter && count($names) > 1 && in_array('tablespace', $names, true)) {
            $derivation->report(new CatalogMisuse(CatalogMisuseRule::ExclusiveOption, ['tablespace']));
        }
    }
}
