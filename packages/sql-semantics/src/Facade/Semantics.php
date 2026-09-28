<?php

declare(strict_types=1);

namespace SqlSemantics\Facade;

use InvalidArgumentException;
use SqlSemantics\Core\Analysis\Analyzer;
use SqlSemantics\Core\Builder;
use SqlSemantics\Core\Declarations;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Mode;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Core\SearchPath;
use SqlSemantics\Statement\Statement;

/**
 * Structures every statement of a selected SQL language into independent values.
 *
 * This entry point needs no database connection. Statements are read as the
 * server reads them: with the grammar of one release, under the session
 * settings given as the mode, and with the selected parameter markers. A
 * statement analyzed with its dependencies, the declarations that came
 * before it, also resolves every table name it writes, reading a name
 * without a schema in the schemas of the session's search path; a name no
 * dependency declares is an error unless the declarations are partial.
 *
 * @visibility public
 * @example Reconstructing SQL with the SQLite database package
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $semantics->analyze('DROP TABLE example')->toString() // => 'DROP TABLE example'
 * @example Resolving a query against the declaration it depends on
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
 *     $query = $semantics->analyze('SELECT name FROM users WHERE id = 1', [$users]);
 *     $query->resolution?->tables()[0]->table?->name // => 'users'
 * @example Finding the statements of a script
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $semantics->split("SELECT 1; SELECT ';'") // => ['SELECT 1;', " SELECT ';'"]
 */
final class Semantics
{
    private readonly Language $language;
    private readonly Analyzer $analyzer;

    /**
     * @var non-empty-list<string>
     */
    private readonly array $searchPath;

    /**
     * Selects the dialect and optionally one of its shipped grammar releases, a mode, and a parameter syntax.
     *
     * @param Dialect $dialect The database
     * @param string|null $grammarVersion A release tag the dialect ships, or null for its default
     * @param Mode|null $mode The session settings SQL is read under, or null for the server's defaults
     * @param Parameters $parameters Which parameter markers are read; the named syntax adds `:name`
     * @param SearchPath|null $searchPath The schemas the session reads an unqualified table name in, such as MySQL's current database, or null for the server's default
     *
     * @throws InvalidArgumentException When the mode does not belong to the dialect, or the database cannot search the path
     */
    public function __construct(Dialect $dialect, ?string $grammarVersion = null, ?Mode $mode = null, Parameters $parameters = Parameters::Native, ?SearchPath $searchPath = null)
    {
        $this->language = new Language($dialect, $grammarVersion, $mode, $parameters);
        $this->searchPath = $dialect->platform()->searchPath($searchPath);
        $this->analyzer = new Analyzer($this->language, $this->searchPath);
    }

    /**
     * Answers the resolved language: dialect, release, mode, and parameter syntax.
     */
    public function language(): Language
    {
        return $this->language;
    }

    /**
     * Answers the schemas an unqualified table name is read in, in order; an unqualified declaration creates its table in the first.
     *
     * @return non-empty-list<string>
     */
    public function searchPath(): array
    {
        return $this->searchPath;
    }

    /**
     * Builds an immutable statement from the SQL of one statement, resolved against its dependencies when they are given.
     *
     * Without dependencies the statement is structured only. With them, even
     * none, the statement is also resolved: the tables it declares are read,
     * and every table name it writes must be a common table expression it
     * defines, a table a dependency declares, or a table it declares or
     * drops itself. Dependencies are applied in order, so a later DROP TABLE
     * removes an earlier declaration. With partial declarations, a name no
     * dependency declares is an undeclared table instead of an error.
     *
     * @param list<Statement>|null $dependencies The declarations the statement is read against, in order
     * @param Declarations $declarations Whether the dependencies declare every table of the database, or only some
     *
     * @throws \SqlSemantics\Core\AnalysisException When SQL is not one statement of the selected language
     * @throws \SqlSemantics\Core\SemanticException When a table name resolves to nothing under complete declarations or a declaration conflicts with a dependency
     */
    public function analyze(string $sql, ?array $dependencies = null, Declarations $declarations = Declarations::Complete): Statement
    {
        return $this->analyzer->analyze($sql, $dependencies, $declarations);
    }

    /**
     * Builds one immutable statement for each statement of a script, in order, each resolved against the dependencies and the statements before it when dependencies are given.
     *
     * @param list<Statement>|null $dependencies
     * @return list<Statement>
     * @throws \SqlSemantics\Core\AnalysisException When a statement is not in the selected language
     * @throws \SqlSemantics\Core\SemanticException When a table name resolves to nothing under complete declarations or a declaration conflicts
     */
    public function analyzeAll(string $sql, ?array $dependencies = null, Declarations $declarations = Declarations::Complete): array
    {
        return $this->analyzer->analyzeAll($sql, $dependencies, $declarations);
    }

    /**
     * Finds the statement boundaries of a script, as the server finds them.
     *
     * Each text ends with its own terminator, and trailing whitespace and
     * comments stay with the last statement. A semicolon inside a string, a
     * comment, or a compound statement ends nothing.
     *
     * @return list<string>
     * @throws \SqlSemantics\Core\AnalysisException When a statement is not in the selected language
     */
    public function split(string $sql): array
    {
        return $this->analyzer->split($sql);
    }

    /**
     * Decodes a literal without evaluating an expression or applying a column type.
     *
     * Numeric values remain exact decimal text. SQL NULL has its own variant;
     * a value requiring evaluation throws rather than pretending to be NULL.
     * @throws \SqlSemantics\Core\Literal\DecodingException When the value is not a decodable literal
     */
    public function decodeLiteral(\SqlSemantics\Statement\Element $value): \SqlSemantics\Statement\Literal\Literal
    {
        return (new \SqlSemantics\Core\Literal\Reader($this->language))->read($value);
    }

    /**
     * Reads a standalone column type, keeping its syntax, declared facts, and effective numeric size.
     *
     * @throws \SqlSemantics\Core\AnalysisException When input is not exactly one type
     * @throws \SqlSemantics\Core\SemanticException When the declared type has invalid parameters
     */
    public function type(string $sql): \SqlSemantics\Statement\Declaration\TypeDeclaration
    {
        return (new \SqlSemantics\Core\Ast\TypeInput($this->language))->read($sql);
    }

    /**
     * Answers the composer of values for this language, spelling names and literals as the release and mode read them.
     */
    public function builder(): Builder
    {
        return $this->language->dialect->platform()->builder($this->language);
    }
}
