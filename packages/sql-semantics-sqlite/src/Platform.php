<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Parser\SqlParser;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Core\Analysis\TriviaReader;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Platform as Contract;
use SqlSemantics\Core\Policy;

/**
 * Assembles Sqlite semantic behavior from independent core contracts.
 *
 * @visibility SqlSemantics
 */
final class Platform implements Contract
{
    /**
     * Retains the public language identity in all semantic types.
     */
    public function __construct(private readonly Dialect $dialect)
    {
    }

    /**
     * Configures the selected grammar release.
     */
    public function parser(?string $version = null): SqlParser
    {
        return new SqliteParser($version);
    }

    /**
     * Loads this package's statement construction map for the resolved release.
     */
    public function values(string $version): \SqlSemantics\Core\Analysis\ValueReader
    {
        return \SqlSemantics\Core\Analysis\ValueReader::fromFile(dirname(__DIR__) . '/resources/mapping/' . basename($version) . '.php', new TriviaReader());
    }

    /**
     * Supplies the default declaration namespace.
     */
    public function defaultSchema(): string
    {
        return 'main';
    }

    /**
     * @return array{string, string}
     */
    public function statementNames(): array
    {
        return ['input', 'cmd'];
    }

    /**
     * Maps the supported grammar productions onto semantic roles.
     */
    public function syntax(): Policy\SyntaxRules
    {
        return new Policy\SyntaxRules([
            'columnName' => ['nm'],
            'declaredType' => ['typetoken'],
            'expression' => ['expr'],
            'createTable' => ['create_table'],
            'createHeader' => ['create_table'],
            'tableName' => ['nm'],
            'tableConstraint' => ['tcons'],
            'columnReference' => [],
            'identifierToken' => ['ID'],
            'parameterToken' => ['VARIABLE'],
            'projectionList' => [],
            'projectionExpression' => ['expr'],
            'projectionAlias' => ['as'],
            'selectStatement' => ['select'],
            'selectBody' => ['oneselect'],
            'from' => ['from'],
            'where' => ['where_opt'],
            'selectOptions' => ['distinct'],
            'orderingChildren' => ['sortlist', 'expr', 'sortorder', 'nulls'],
            'orderingDirection' => ['sortorder'],
            'nullsOrder' => ['nulls'],
            'stringToken' => ['STRING'],
            'limit' => ['limit_opt'],
            'offset' => [],
            'paginationExpression' => ['expr'],
            'selectChildren' => ['distinct', 'selcollist', 'from', 'where_opt', 'orderby_opt', 'limit_opt'],
            'unsupportedModifier' => ['with'],
            'relation' => ['seltablist'],
            'qualifiedExpression' => ['expr'],
            'qualifiedPart' => ['nm'],
        ]);
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
        return new TypeRules($this->dialect);
    }

    /**
     * Supplies schema semantics.
     */
    public function schema(): Policy\SchemaRules
    {
        return new SchemaRules();
    }

    /**
     * Supplies query semantics.
     */
    public function query(): Policy\QueryRules
    {
        return new QueryRules();
    }
}
