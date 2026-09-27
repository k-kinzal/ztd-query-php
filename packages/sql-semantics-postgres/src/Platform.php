<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use InvalidArgumentException;
use SqlParser\Parser\SqlParser;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Core\Analysis\TriviaReader;
use SqlSemantics\Core\Builder as Composer;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Mode as SessionMode;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Core\Platform as Contract;
use SqlSemantics\Core\Policy;

/**
 * Assembles PostgreSql semantic behavior from independent core contracts.
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
     * Supplies the default declaration namespace.
     */
    public function defaultSchema(): string
    {
        return 'public';
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
            'dropTableName' => ['any_name'],
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

}
