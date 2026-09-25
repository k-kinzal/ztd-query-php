<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\ColumnReader;
use SqlSemantics\Ast\Declaration\ColumnDefinition;
use SqlSemantics\Ast\Declaration\TableConstraint;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\ConstraintKind;

/**
 * Finds MySQL column declarations whose NULL writing the server rejects: every release rejects DEFAULT NULL on a
 * column that is NOT NULL, whether written so or made so by its own PRIMARY KEY attribute (error 1067), and a primary
 * key part written NULL or DEFAULT NULL is silently declared NOT NULL by MySQL 5.6 but rejected by MySQL 5.7.3 and
 * later (error 1171), where a later DEFAULT clause lifts an earlier DEFAULT NULL only in the releases {@see self::replacesDefault()} lists.
 *
 * @visibility SqlSemantics
 */
final class PrimaryKeyNulls
{
    /**
     * The first release that rejects a NULL primary key part, numbered as major * 10000 + minor * 100 + patch.
     */
    public const REJECTING_RELEASE = 50703;

    /**
     * The release ranges, each from its first release up to but excluding its end, in which a later DEFAULT clause of a
     * declaration replaces an earlier DEFAULT NULL for the primary key check: MySQL 8.0.43, 8.4.6 and 9.4.0 made the
     * change, so 8.0.42, 8.1 to 8.3, 8.4.0 to 8.4.5 and 9.0 to 9.3 reject `DEFAULT NULL DEFAULT 1 PRIMARY KEY` as 5.7 does.
     */
    public const REPLACING_DEFAULT_RELEASES = [[80043, 80100], [80406, 80500], [90400, PHP_INT_MAX]];

    /**
     * The release a declaration is read against when none is given: MySQL 8.4.7, the default grammar release.
     */
    public const DEFAULT_RELEASE = 80407;

    /**
     * Rejects a NOT NULL column declared DEFAULT NULL, and a column written NULL or DEFAULT NULL that belongs to a primary key written in the same statement.
     *
     * @param list<ColumnDefinition> $columns Column declarations written in the statement
     * @param list<TableConstraint> $constraints Column and table constraints written in the statement
     * @throws \SqlSemantics\InvalidSql
     */
    public static function reject(array $columns, array $constraints, Dialect $dialect, ?string $grammarVersion): void
    {
        if ($dialect !== Dialect::MySql) {
            return;
        }
        foreach ($columns as $column) {
            $default = self::nullDefault($column);
            if ($default !== null) {
                throw new \SqlSemantics\InvalidSql(InputViolation::NullDefault, $default);
            }
        }
        $release = ReplicationRelease::number($grammarVersion);
        if ($release < self::REJECTING_RELEASE) {
            return;
        }
        $primary = [];
        foreach ($constraints as $constraint) {
            if ($constraint->kind === ConstraintKind::PrimaryKey) {
                array_push($primary, ...array_map(strtolower(...), $constraint->columns));
            }
        }
        foreach ($columns as $column) {
            $written = self::writtenNull($column, $release);
            if ($written !== null && in_array(strtolower($column->name), $primary, true)) {
                throw new \SqlSemantics\InvalidSql(InputViolation::NullablePrimaryKey, $written);
            }
        }
    }

    /**
     * Reports whether a later DEFAULT clause replaces an earlier DEFAULT NULL for the primary key check in the release.
     */
    public static function replacesDefault(int $release): bool
    {
        foreach (self::REPLACING_DEFAULT_RELEASES as [$first, $end]) {
            if ($release >= $first && ($release < $end || $end === PHP_INT_MAX)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the NULL attribute written on the declaration, or else the DEFAULT NULL attribute that the release reads as
     * declaring the column nullable: the last DEFAULT clause where {@see self::replacesDefault()} holds, any DEFAULT NULL
     * elsewhere; null when it writes neither.
     */
    public static function writtenNull(ColumnDefinition $column, int $release = self::DEFAULT_RELEASE): ?Node
    {
        $replaces = self::replacesDefault($release);
        $default = null;
        foreach ($column->attributes as $attribute) {
            $words = ColumnReader::attributeWords($attribute);
            if ($words === ['NULL']) {
                return $attribute;
            }
            if (($words[0] ?? '') === 'DEFAULT' && ($words === ['DEFAULT', 'NULL'] || $replaces)) {
                $default = $words === ['DEFAULT', 'NULL'] ? $attribute : null;
            }
        }
        return $default;
    }

    /**
     * Returns the DEFAULT NULL attribute of a declaration that ends NOT NULL, or null: the last of NULL, NOT NULL and a
     * column PRIMARY KEY decides, the last literal DEFAULT clause is the literal default, which a DEFAULT expression
     * does not replace, and AUTO_INCREMENT exempts the column.
     */
    public static function nullDefault(ColumnDefinition $column): ?Node
    {
        $notNull = false;
        $default = null;
        $counter = false;
        foreach ($column->attributes as $attribute) {
            $words = ColumnReader::attributeWords($attribute);
            if (in_array($words, [['NOT', 'NULL'], ['NULL'], ['PRIMARY', 'KEY'], ['KEY']], true)) {
                $notNull = $words !== ['NULL'];
            } elseif (($words[0] ?? '') === 'DEFAULT') {
                $default = $words === ['DEFAULT', 'NULL'] ? $attribute : (\SqlSemantics\Ast\Tree::child($attribute, ['expr']) === null ? null : $default);
            }
            $counter = $counter || in_array($words, [['AUTO_INCREMENT'], ['SERIAL', 'DEFAULT', 'VALUE']], true);
        }
        return $notNull && !$counter ? $default : null;
    }
}
