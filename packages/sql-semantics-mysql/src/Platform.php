<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use InvalidArgumentException;
use SqlParser\MySql\MySqlParser;
use SqlParser\MySql\MySqlVersion;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Core\Analysis\TriviaReader;
use SqlSemantics\Core\Builder as Composer;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Mode as SessionMode;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Core\Platform as Contract;
use SqlSemantics\Core\Policy;

/**
 * Assembles MySql semantic behavior from independent core contracts.
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
     * Supplies the default declaration namespace.
     */
    public function defaultSchema(): string
    {
        return '';
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
            'dropTableName' => ['table_ident'],
            'generationStorage' => ['opt_stored_attribute'],
            'generationClause' => ['field_def'],
            'statementRoot' => ['query'],
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
