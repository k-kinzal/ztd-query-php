<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use InvalidArgumentException;
use SqlParser\MySql\MySqlParser;
use SqlParser\MySql\MySqlVersion;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Core\Analysis\TriviaReader;
use SqlSemantics\Core\Builder as Composer;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Mode as SessionMode;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Core\Platform as Contract;
use SqlSemantics\Core\Policy;
use SqlSemantics\Core\SearchPath as SessionSearchPath;

/**
 * Assembles MySql semantic behavior from independent core contracts.
 *
 * @visibility SqlSemantics
 */
final class Platform implements Contract
{
    /**
     * Configures the selected grammar release under the session's `sql_mode` and parameter syntax.
     *
     * @throws InvalidArgumentException When the mode is not this database's Mode
     */
    public function parser(?string $version = null, ?SessionMode $mode = null, Parameters $parameters = Parameters::Native): SqlParser
    {
        if ($mode !== null && !$mode instanceof Mode) {
            throw new InvalidArgumentException('The mode must be a ' . Mode::class . ', ' . $mode::class . ' given.');
        }

        return new MySqlParser($version, $mode === null ? new \SqlParser\MySql\SqlMode() : $mode->sqlMode, parameters: $parameters->syntax());
    }

    /**
     * Composes this database's values for a language.
     */
    public function builder(Language $language): Composer
    {
        return new Builder($language);
    }

    /**
     * Loads this package's statement construction map for the resolved release.
     */
    public function values(string $version): \SqlSemantics\Core\Analysis\ValueReader
    {
        return \SqlSemantics\Core\Analysis\ValueReader::fromFile(dirname(__DIR__) . '/resources/mapping/' . basename($version) . '.php', new TriviaReader(executableVersion: MySqlVersion::resolve($version)->id()));
    }

    /**
     * Reads unqualified names in the current database, the one schema of the path; without one, in an unnamed database of their own.
     *
     * @throws InvalidArgumentException When the path has more than one schema, as MySQL has one current database
     */
    public function searchPath(?SessionSearchPath $path = null): array
    {
        if ($path === null) {
            return [''];
        }
        if (count($path->schemas) !== 1) {
            throw new InvalidArgumentException('MySQL reads unqualified names in its one current database, ' . count($path->schemas) . ' schemas given.');
        }

        return $path->schemas;
    }

    /**
     * @return array{string, string}
     */
    public function statementNames(): array
    {
        return ['start_entry', 'simple_statement'];
    }

    /**
     * Maps the supported grammar productions onto semantic roles.
     */
    public function syntax(): Policy\SyntaxRules
    {
        return new Policy\SyntaxRules([
            'autoIncrement' => [],
            'generationStorage' => ['opt_stored_attribute'],
            'generationClause' => ['field_def'],
            'statement' => ['statement'],
            'columnName' => ['field_ident', 'ident'],
            'declaredType' => ['type'],
            'expression' => ['expr'],
            'tableElements' => ['table_element_list', 'create_field_list'],
            'createTable' => ['create_table_stmt', 'create'],
            'createHeader' => [],
            'tableName' => ['table_ident'],
            'tableConstraint' => ['table_constraint_def'],
        ]);
    }

    /**
     * Names the positions where the grammar writes table names, and the forms that declare, drop, or merely name tables.
     *
     * The body of a common table expression names the ones written before it
     * in its WITH clause, and itself only when the clause is recursive; a
     * later one is not visible even then. The table an UPDATE or DELETE
     * writes to is resolved like any other name, so it can be one.
     */
    public function relations(): Policy\RelationRules
    {
        return new Policy\RelationRules(
            nameSymbols: ['table_ident', 'table_name', 'table_list'],
            declarations: [
                ['rule' => 'create_table_stmt', 'name' => 'table_ident', 'conditional' => 'opt_if_not_exists'],
                ['rule' => 'create', 'requires' => ['TABLE_SYM'], 'name' => 'table_ident', 'conditional' => 'opt_if_not_exists'],
            ],
            drops: [
                ['rule' => 'drop_table_stmt', 'names' => 'table_list', 'list' => ['table_list', ['table_list', ',', 'table_ident']], 'conditional' => 'if_exists'],
                ['rule' => 'drop', 'requires' => ['table_or_tables'], 'names' => 'table_list', 'list' => ['table_list', ['table_list', ',', 'table_name']], 'conditional' => 'if_exists'],
            ],
            commonTableExpressions: [['rule' => 'common_table_expr', 'name' => 'ident']],
            ignored: [
                ['rule' => 'view_tail', 'name' => 'table_ident'],
                ['rule' => 'drop_view_stmt', 'name' => 'table_list'],
                ['rule' => 'drop', 'requires' => ['VIEW_SYM'], 'name' => 'table_list'],
                ['rule' => 'table_to_table', 'pair' => ['table_ident', 'table_ident']],
                ['rule' => 'alter_list_item', 'requires' => ['RENAME', 'table_ident'], 'name' => 'table_ident'],
            ],
            withClauses: ['opt_with_clause', 'with_clause'],
            recursive: 'RECURSIVE_SYM',
            visibility: Policy\WithVisibility::Preceding,
            recursiveVisibility: Policy\WithVisibility::PrecedingAndItself,
        );
    }

    /**
     * Supplies names semantics.
     */
    public function names(): Policy\NameRules
    {
        return new NameRules();
    }

    /**
     * Supplies types semantics.
     */
    public function types(): Policy\TypeRules
    {
        return new TypeRules();
    }

    /**
     * Supplies schema semantics.
     */
    public function schema(): Policy\SchemaRules
    {
        return new SchemaRules();
    }

}
