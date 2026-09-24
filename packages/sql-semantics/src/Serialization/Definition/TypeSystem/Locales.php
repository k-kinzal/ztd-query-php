<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\TypeSystem;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Collation\CollationProvider;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Locale as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Writes collation and encoding conversion commands.
 * @visibility SqlSemantics
 */
final class Locales
{
    /**
     * Returns null for statements outside these forms.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        return match (true) {
            $statement instanceof Statement\RefreshCollationVersionStatement => new Tree('alter-collation', [Build::keyword('ALTER COLLATION'), Build::identifier($statement->collation->parts, $dialect), Build::keyword('REFRESH VERSION')]),
            $statement instanceof Statement\CreateConversionStatement => new Tree('create-conversion', [Build::keyword($statement->isDefault ? 'CREATE DEFAULT CONVERSION' : 'CREATE CONVERSION'), Build::identifier($statement->name->parts, $dialect), Build::keyword('FOR'), TypeDefinitions::text($statement->sourceEncoding->value), Build::keyword('TO'), TypeDefinitions::text($statement->targetEncoding->value), Build::keyword('FROM'), Build::identifier($statement->function->parts, $dialect)]),
            $statement instanceof Statement\CopyCollationStatement => new Tree('create-collation', [Build::keyword($statement->ifNotExists ? 'CREATE COLLATION IF NOT EXISTS' : 'CREATE COLLATION'), Build::identifier($statement->name->parts, $dialect), Build::keyword('FROM'), Build::identifier($statement->copied->parts, $dialect)]),
            $statement instanceof Statement\CreateCollationStatement => new Tree('create-collation', [Build::keyword($statement->ifNotExists ? 'CREATE COLLATION IF NOT EXISTS' : 'CREATE COLLATION'), Build::identifier($statement->name->parts, $dialect), Build::parentheses(Build::separated(self::settings($statement)))]),
            default => null,
        };
    }

    /**
     * The collation settings that differ from their defaults.
     * @return list<Tree>
     * @throws InvalidStructure
     */
    public static function settings(Statement\CreateCollationStatement $statement): array
    {
        $settings = [];
        foreach (['PROVIDER' => $statement->provider === CollationProvider::Libc ? null : $statement->provider->value, 'LOCALE' => $statement->locale, 'LC_COLLATE' => $statement->lcCollate, 'LC_CTYPE' => $statement->lcCtype] as $key => $value) {
            if ($value !== null) {
                $settings[] = new Tree('collation-setting', [Build::keyword($key . ' ='), TypeDefinitions::text($value)]);
            }
        }
        if (!$statement->deterministic) {
            $settings[] = Build::keyword('DETERMINISTIC = FALSE');
        }
        foreach (['RULES' => $statement->rules, 'VERSION' => $statement->version] as $key => $value) {
            if ($value !== null) {
                $settings[] = new Tree('collation-setting', [Build::keyword($key . ' ='), TypeDefinitions::text($value)]);
            }
        }
        return $settings;
    }
}
