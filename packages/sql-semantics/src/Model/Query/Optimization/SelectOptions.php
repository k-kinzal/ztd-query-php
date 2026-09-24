<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Optimization;

use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates the MySQL query block options of one SELECT.
 * @visibility SqlSemantics
 */
final class SelectOptions
{
    /**
     * Requires MySQL, each option once, not both query cache options, and SQL_CACHE only before MySQL 8.0.
     * @param list<SelectOption> $options
     * @throws InvalidStructure
     */
    public static function validate(array $options, Origin $origin): void
    {
        \SqlSemantics\Model\Validation\Collections::objects($options, SelectOption::class);
        if ($options === []) {
            return;
        }
        if ($origin->dialect !== \SqlSemantics\Dialect::MySql) {
            throw new InvalidStructure('Query block options require MySQL.');
        }
        if (count(array_unique(array_map(static fn (SelectOption $option): string => $option->value, $options))) !== count($options)) {
            throw new InvalidStructure('A query block gives each option once.');
        }
        if (in_array(SelectOption::Cache, $options, true) && in_array(SelectOption::NoCache, $options, true)) {
            throw new InvalidStructure('SQL_CACHE and SQL_NO_CACHE exclude each other.');
        }
        $release = $origin->context?->schema()->grammarVersion;
        if (in_array(SelectOption::Cache, $options, true) && $release !== null && !str_starts_with($release, 'mysql-5.')) {
            throw new InvalidStructure('SQL_CACHE exists only before MySQL 8.0.');
        }
    }
}
