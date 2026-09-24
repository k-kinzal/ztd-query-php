<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\SettingTokens;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseOption;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds PostgreSQL CREATE, ALTER and DROP DATABASE without consulting the cluster catalog.
 * @visibility SqlSemantics
 */
final class DatabaseCommands
{
    /**
     * Returns the database operation of a CreatedbStmt, AlterDatabaseStmt, AlterDatabaseSetStmt or DropdbStmt.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): BoundStatement
    {
        $identifiers = $context->tables->identifiers;
        $names = array_map(static fn (Node $name): string => $identifiers->name($name->tokens()[0]), Tree::outer($source, ['name']));
        $name = $names[0] ?? throw new UnclassifiedSql('A database operation requires its database name.');
        $words = SettingTokens::words($source->tokens());
        try {
            return match ($source->name) {
                'CreatedbStmt' => new Statement\CreateDatabaseStatement($origin, $name, self::options($source, $identifiers)),
                'DropdbStmt' => new Statement\DropDatabaseStatement($origin, $name, ($words[2] ?? '') === 'IF', Tree::child($source, ['drop_option_list']) !== null),
                'AlterDatabaseSetStmt' => self::setting($origin, $source, $name, new Scope($identifiers, queries: $context)),
                default => self::alteration($origin, $source, $name, $names, $words, $identifiers),
            };
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::DatabaseOption, $source, $error);
        }
    }

    /**
     * Separates tablespace moves and collation refreshes from property changes.
     * @param list<string> $names Database name followed by the destination tablespace, if any
     * @param list<string> $words Upper-case statement terminals
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function alteration(Origin $origin, Node $source, string $name, array $names, array $words, Identifiers $identifiers): BoundStatement
    {
        if (isset($names[1])) {
            return new Statement\SetDatabaseTablespaceStatement($origin, $name, $names[1]);
        }
        if (in_array('REFRESH', $words, true) && Tree::child($source, ['createdb_opt_list']) === null) {
            return new Statement\RefreshDatabaseCollationStatement($origin, $name);
        }
        $options = self::options($source, $identifiers);
        foreach ($options as $option) {
            if ($option->parameter === DatabaseParameter::Tablespace) {
                if (count($options) !== 1 || !is_string($option->value)) {
                    throw new InvalidSql(InputViolation::DatabaseOption, $source);
                }
                return new Statement\SetDatabaseTablespaceStatement($origin, $name, $option->value);
            }
        }
        return new Statement\AlterDatabaseOptionsStatement($origin, $name, $options);
    }

    /**
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function setting(Origin $origin, Node $source, string $name, Scope $scope): BoundStatement
    {
        $clause = Tree::child($source, ['SetResetClause']) ?? throw new UnclassifiedSql('A database setting requires its SET or RESET clause.');
        $reset = Tree::child($clause, ['VariableResetStmt']);
        if ($reset === null) {
            return new Statement\AlterDatabaseSetStatement($origin, $name, StoredSettings::assignment($clause, $scope));
        }
        $setting = StoredSettings::reset($origin, $reset, $scope);
        return $setting === null ? new Statement\AlterDatabaseResetAllStatement($origin, $name) : new Statement\AlterDatabaseResetStatement($origin, $name, $setting);
    }

    /**
     * Reads each named property and converts its argument to the parameter's value domain.
     * @return list<DatabaseOption>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function options(Node $source, Identifiers $identifiers): array
    {
        $options = [];
        foreach (Tree::outer($source, ['createdb_opt_item']) as $item) {
            $label = Tree::child($item, ['createdb_opt_name']) ?? throw new UnclassifiedSql('A database option requires its name.');
            $tokens = $label->tokens();
            $parameter = DatabaseParameter::named(count($tokens) === 2 ? 'connection_limit' : $identifiers->name($tokens[0]));
            $argument = Tree::child($item, ['NumericOnly', 'opt_boolean_or_string']);
            if ($parameter === null) {
                throw new InvalidSql(InputViolation::DatabaseOption, $item);
            }
            try {
                $options[] = new DatabaseOption($parameter, $argument === null ? null : self::value($parameter, OptionWords::raw($argument, $identifiers)));
            } catch (InvalidStructure $error) {
                throw new InvalidSql(InputViolation::DatabaseOption, $item, $error);
            }
        }
        return $options;
    }

    /**
     * Converts a decoded argument as the server's option readers do; unconvertible values stay unchanged for validation.
     */
    public static function value(DatabaseParameter $parameter, string|int $raw): string|int|bool
    {
        return match ($parameter) {
            DatabaseParameter::AllowConnections, DatabaseParameter::IsTemplate => OptionWords::boolean($raw) ?? (string) $raw,
            DatabaseParameter::ConnectionLimit, DatabaseParameter::Encoding => $raw,
            DatabaseParameter::Oid => is_string($raw) && preg_match('/^[0-9]{1,10}$/D', $raw) === 1 ? (int) $raw : $raw,
            DatabaseParameter::Owner, DatabaseParameter::Template, DatabaseParameter::Strategy, DatabaseParameter::Locale, DatabaseParameter::LcCollate, DatabaseParameter::LcCtype, DatabaseParameter::IcuLocale, DatabaseParameter::IcuRules,
            DatabaseParameter::LocaleProvider, DatabaseParameter::BuiltinLocale, DatabaseParameter::CollationVersion, DatabaseParameter::Tablespace, DatabaseParameter::Location => (string) $raw,
        };
    }
}
