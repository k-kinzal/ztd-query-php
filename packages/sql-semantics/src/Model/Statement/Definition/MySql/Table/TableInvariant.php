<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Table;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Validates the operands shared by MySQL table definition statements.
 * @visibility SqlSemantics
 */
final class TableInvariant
{
    /**
     * Requires MySQL and a physical table named without a query alias.
     * @throws InvalidStructure
     */
    public static function table(Origin $origin, TableReference $table): void
    {
        self::dialect($origin);
        if ($table->alias !== null) {
            throw new InvalidStructure('A table definition statement cannot use a query alias.');
        }
        if (count($table->name->parts) > 2) {
            throw new InvalidStructure('A MySQL table name has at most a database and a table component.');
        }
        StatementOperands::relation($table, $origin->dialect);
    }

    /**
     * Requires the MySQL dialect.
     * @throws InvalidStructure
     */
    public static function dialect(Origin $origin): void
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('MySQL table definition statements require MySQL.');
        }
    }

    /**
     * Returns the grammar release of the statement, or null when no schema snapshot names one.
     */
    public static function release(Origin $origin): ?string
    {
        return $origin->context?->schema()->grammarVersion;
    }

    /**
     * Requires a grammar release where MySQL 8 table forms exist.
     * @throws InvalidStructure
     */
    public static function modern(Origin $origin, string $form): void
    {
        if (in_array(self::release($origin), ['mysql-5.6.51', 'mysql-5.7.44'], true)) {
            throw new InvalidStructure($form . ' requires MySQL 8.0 or later.');
        }
    }

    /**
     * Requires a grammar release where MySQL 5.7 table forms exist.
     * @throws InvalidStructure
     */
    public static function since57(Origin $origin, string $form): void
    {
        if (self::release($origin) === 'mysql-5.6.51') {
            throw new InvalidStructure($form . ' requires MySQL 5.7 or later.');
        }
    }

    /**
     * Requires one of the listed grammar releases when the statement names a release.
     * @param non-empty-list<string> $releases
     * @throws InvalidStructure
     */
    public static function only(Origin $origin, array $releases, string $form): void
    {
        $release = self::release($origin);
        if ($release !== null && !in_array($release, $releases, true)) {
            throw new InvalidStructure($form . ' is not defined by grammar release ' . $release . '.');
        }
    }
}
