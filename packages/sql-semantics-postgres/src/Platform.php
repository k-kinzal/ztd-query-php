<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use InvalidArgumentException;
use SqlParser\Parser\SqlParser;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Core\Analysis\TriviaReader;
use SqlSemantics\Core\Builder as Composer;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Mode as SessionMode;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Core\Platform as Contract;
use SqlSemantics\Core\Policy;
use SqlSemantics\Core\SearchPath as SessionSearchPath;

/**
 * Assembles PostgreSql semantic behavior from independent core contracts.
 *
 * @visibility SqlSemantics
 */
final class Platform implements Contract
{
    /**
     * Configures the selected grammar release and parameter syntax; this database has no session mode.
     *
     * @throws InvalidArgumentException When a mode is given
     */
    public function parser(?string $version = null, ?SessionMode $mode = null, Parameters $parameters = Parameters::Native): SqlParser
    {
        if ($mode !== null) {
            throw new InvalidArgumentException('This database reads SQL under no session mode; ' . $mode::class . ' given.');
        }

        return new PostgreSqlParser($version, parameters: $parameters->syntax());
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
        return \SqlSemantics\Core\Analysis\ValueReader::fromFile(dirname(__DIR__) . '/resources/mapping/' . basename($version) . '.php', new TriviaReader(nestedBlocks: true));
    }

    /**
     * Reads unqualified names in the schemas of the `search_path`, by default `public`.
     */
    public function searchPath(?SessionSearchPath $path = null): array
    {
        return $path === null ? ['public'] : $path->schemas;
    }

    /**
     * @return array{string, string}
     */
    public function statementNames(): array
    {
        return ['parse_toplevel', 'stmt'];
    }

    /**
     * Maps the supported grammar productions onto semantic roles.
     */
    public function syntax(): Policy\SyntaxRules
    {
        return new Policy\SyntaxRules([
            'autoIncrement' => [],
            'generationStorage' => ['ColConstraintElem'],
            'generationClause' => [],
            'columnName' => ['ColId'],
            'declaredType' => ['Typename'],
            'expression' => ['a_expr'],
            'tableElements' => ['OptTableElementList'],
            'createTable' => ['CreateStmt'],
            'createHeader' => [],
            'tableName' => ['qualified_name'],
            'tableConstraint' => ['TableConstraint'],
        ]);
    }

    /**
     * Names the positions where the grammar writes table names, and the forms that declare, drop, or merely name tables.
     *
     * The body of a common table expression names the ones written before it
     * in a plain WITH clause, and every one of a recursive clause, itself
     * and later ones included. The table INSERT, UPDATE, DELETE, or MERGE
     * writes to is always a table, never a common table expression.
     */
    public function relations(): Policy\RelationRules
    {
        return new Policy\RelationRules(
            nameSymbols: ['qualified_name', 'relation_expr', 'relation_expr_opt_alias', 'insert_target', 'qualified_name_list', 'relation_expr_list'],
            declarations: [
                ['rule' => 'CreateStmt', 'name' => 'qualified_name', 'conditional' => 'IF_P'],
                ['rule' => 'CreateAsStmt', 'name' => 'create_as_target', 'conditional' => 'IF_P'],
            ],
            drops: [
                ['rule' => 'DropStmt', 'requires' => ['object_type_any_name'], 'type' => ['object_type_any_name', ['TABLE']], 'names' => 'any_name_list', 'list' => ['any_name_list', ['any_name_list', ',', 'any_name']], 'conditional' => 'IF_P'],
            ],
            commonTableExpressions: [['rule' => 'common_table_expr', 'name' => 'name']],
            ignored: [['rule' => 'ViewStmt', 'name' => 'qualified_name']],
            parts: ['create_as_target' => ['qualified_name opt_column_list table_access_method_clause OptWith OnCommitOption OptTableSpace' => [0]]],
            withClauses: ['opt_with_clause', 'with_clause'],
            recursive: 'RECURSIVE',
            visibility: Policy\WithVisibility::Preceding,
            recursiveVisibility: Policy\WithVisibility::All,
            targets: ['insert_target', 'relation_expr_opt_alias'],
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
