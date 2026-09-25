<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Grouping;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Validates a GROUP BY list against the grouping forms of its dialect and grammar release.
 * @visibility SqlSemantics
 */
final class GroupingRules
{
    /**
     * Releases whose grammar has no `GROUP BY CUBE(...)`.
     */
    public const WITHOUT_CUBE = ['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0'];

    /**
     * Requires SQLite to group by plain keys, MySQL to group by plain keys or by one ROLLUP or CUBE (CUBE from MySQL
     * 8.3, descending keys only in MySQL 5.x), PostgreSQL to use no descending keys, and only PostgreSQL to remove
     * duplicate grouping sets.
     *
     * @param list<Expression|GroupingConstruct|DescendingGroupKey> $groupBy
     * @throws InvalidStructure
     */
    public static function validate(array $groupBy, Origin $origin, bool $distinct): void
    {
        Collections::alternatives($groupBy, [Expression::class, GroupingConstruct::class, DescendingGroupKey::class]);
        $members = self::members($groupBy);
        StatementOperands::expressions(array_values(array_filter($members, static fn (object $member): bool => $member instanceof Expression)), $origin->dialect);
        $constructs = array_values(array_filter($members, static fn (object $member): bool => $member instanceof GroupingConstruct));
        $descending = array_filter($members, static fn (object $member): bool => $member instanceof DescendingGroupKey) !== [];
        $release = $origin->context?->schema()->grammarVersion;
        if ($distinct && $origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Only PostgreSQL removes duplicate grouping sets with GROUP BY DISTINCT.');
        }
        if ($descending && ($origin->dialect !== Dialect::MySql || ($release !== null && !in_array($release, ['mysql-5.6.51', 'mysql-5.7.44'], true)))) {
            throw new InvalidStructure('Only MySQL 5.x sorts groups by a descending GROUP BY key.');
        }
        if ($constructs !== [] && $origin->dialect === Dialect::Sqlite) {
            throw new InvalidStructure('SQLite groups rows by plain keys only.');
        }
        if ($constructs !== [] && $origin->dialect === Dialect::MySql) {
            if (count($groupBy) !== 1 || count($constructs) !== 1 || !($groupBy[0] instanceof Rollup || $groupBy[0] instanceof Cube)) {
                throw new InvalidStructure('A MySQL GROUP BY is either plain keys or one ROLLUP or CUBE over plain keys.');
            }
            if ($groupBy[0] instanceof Cube && in_array($release, self::WITHOUT_CUBE, true)) {
                throw new InvalidStructure('GROUP BY CUBE requires MySQL 8.3 or later.');
            }
        }
    }

    /**
     * Lists every key, descending key and grouping construct of the list, nested members included.
     *
     * @param list<Expression|GroupingConstruct|DescendingGroupKey> $groupBy
     * @return list<Expression|GroupingConstruct|DescendingGroupKey>
     */
    public static function members(array $groupBy): array
    {
        $members = [];
        foreach ($groupBy as $member) {
            $members[] = $member;
            $nested = match (true) {
                $member instanceof Rollup, $member instanceof Cube => $member->keys,
                $member instanceof GroupingSets => $member->sets,
                $member instanceof DescendingGroupKey => [$member->key],
                default => [],
            };
            array_push($members, ...self::members($nested));
        }
        return $members;
    }
}
