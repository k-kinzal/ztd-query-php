<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use InvalidArgumentException;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\SqlParser;
use SqlSemantics\AnalysisContext as Context;
use SqlSemantics\Analyzer;
use SqlSemantics\Contract\Mode;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupedLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Delete;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\DeleteLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Update;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\UpdateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Compound;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\CompoundLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjectionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Recursive\RecursiveSeriesLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\RecursiveSeries;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RelationChangeLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RenameColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RenameTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\DropTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\PersistentDeclarationLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Query\CreateTableAs;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate\TruncateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateMaterializedView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\RefreshMaterializedView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\RefreshMaterializedViewLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Transaction\Transaction;
use SqlSemantics\Platform\PostgreSql\Ir\Transaction\TransactionLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Contract\SearchPath;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\SemanticException;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Splitter;
use SqlSemantics\Platform\PostgreSql\Model\Facade\StatementBinder;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete as ParsedDelete;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows as ParsedInsert;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertSelect as ParsedInsertQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update as ParsedUpdate;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop as ParsedDrop;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Rename as ParsedRename;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery as ParsedParenthesized;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression as ParsedWith;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation as ParsedCompound;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable as ParsedDerived;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTableAs as ParsedCreateTableAs;
use SqlSemantics\Platform\PostgreSql\Statement\Table\TruncateTable as ParsedTruncateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateMaterializedView as ParsedCreateMaterializedView;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView as ParsedCreateView;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\RefreshMaterializedView as ParsedRefreshMaterializedView;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Begin as ParsedBegin;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Commit as ParsedCommit;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Rollback as ParsedRollback;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointCommand as ParsedSavepointCommand;

/**
 * Binds SQL under a fixed dialect profile and publishes complete semantic values.
 *
 * @visibility public
 * @example Analyzing and rendering a projection
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $semantics->analyze('SELECT 42 AS answer')->profileId() === $semantics->analyze('SELECT 1')->profileId() // => true
 */
final class Semantics implements Analyzer
{
    private readonly LanguageProfile $profile;

    private readonly SqlParser $parser;

    private readonly Catalog $catalog;

    private readonly StatementBinder $binder;

    /**
     * Fixes the language profile: database, grammar release, session mode and parameter style.
     *
     * @param string|null $grammarVersion A release tag the database package ships, or null for its default
     * @param Mode|null $mode The session settings SQL is read under, or null for the defaults of the database
     * @param ParameterStyle $parameters Which parameter markers are read; the named style adds `:name`
     * @param SearchPath|null $searchPath The schemas an unqualified relation name is searched in, or null for the default of the database
     *
     * @throws InvalidArgumentException When the release, mode or search path does not belong to the database
     */
    public function __construct(?string $grammarVersion = null, ?Mode $mode = null, ParameterStyle $parameters = ParameterStyle::Native, ?SearchPath $searchPath = null)
    {
        $platform = Platform::shared();
        $this->profile = $platform->profile($grammarVersion, $mode, $parameters);
        $this->parser = $platform->parser($this->profile);
        $platform->context($this->profile, $searchPath?->schemas, [], false);
        $this->catalog = new Catalog($this->profile, $searchPath?->schemas, $this->parser);
        $this->binder = new StatementBinder($this->parser);
    }

    /**
     * Answers the fixed language profile.
     */
    public function profile(): LanguageProfile
    {
        return $this->profile;
    }

    /**
     * Builds a context from explicit SQL relation definitions.
     *
     * Null describes an open catalog. An explicit list can enumerate the entire catalog.
     * @param array<array-key, mixed>|null $declarations An ordered list of SQL relation definitions
     */
    public function context(?array $declarations = null, bool $complete = true): Context
    {
        if ($declarations === null) {
            return $this->catalog->build(null, $complete);
        }
        Check::input(array_is_list($declarations), 'Catalog definitions must form a list.');
        $definitions = [];
        foreach ($declarations as $definition) {
            Check::input(is_string($definition), 'Catalog definitions must be SQL strings.');
            $definitions[] = $definition;
        }

        return $this->catalog->build($definitions, $complete);
    }

    /**
     * Resolves a statement and returns its complete database-specific representation.
     *
     * @throws AnalysisException When the grammar rejects the SQL
     * @throws SemanticException When the analysis establishes a semantic error
     * @throws InvalidConstruction When the statement cannot be represented in the supported complete IR
     */
    public function analyze(string $sql, ?Context $context = null): Compound|CreateMaterializedView|CreateTable|CreateTableAs|CreateView|Delete|DropIndex|DropTable|DropView|GroupedSelect|Insert|InsertQuery|LocalProjection|RecursiveSeries|RefreshMaterializedView|RenameColumn|RenameTable|Select|Transaction|TruncateTable|Update|ValuesQuery|WithProjection
    {
        $declarations = $this->catalog->resolve($context);
        $operation = $this->binder->resolve($sql, $declarations);
        $statement = $operation->statement;
        return match (true) {
            $statement instanceof ParsedWith && $statement->with?->recursive === true => (new RecursiveSeriesLowering())->lower($operation, $statement),
            $statement instanceof ParsedWith && $statement->with !== null => (new WithLowering())->lower($operation, $statement),
            $statement instanceof ParsedSelect && $statement->from instanceof ParsedDerived => (new LocalProjectionLowering())->lower($operation),
            $statement instanceof ParsedCompound, $statement instanceof ParsedParenthesized => (new CompoundLowering())->lower($operation, $statement),
            $statement instanceof ParsedSelect && (new GroupedLowering())->applies($statement) => (new GroupedLowering())->lower($operation, $statement),
            $statement instanceof ValuesList => (new ValuesLowering())->lower($operation, $statement),
            $statement instanceof ParsedDelete => (new DeleteLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateTable, $statement instanceof ParsedCreateTableAs, $statement instanceof ParsedCreateView, $statement instanceof ParsedCreateMaterializedView => (new PersistentDeclarationLowering())->lower($operation, $statement),
            $statement instanceof ParsedRefreshMaterializedView => (new RefreshMaterializedViewLowering())->lower($operation, $statement),
            $statement instanceof ParsedDrop, $statement instanceof ParsedRename, $statement instanceof ParsedTruncateTable => (new RelationChangeLowering())->lower($operation, $statement),
            $statement instanceof ParsedInsertQuery => (new InsertLowering())->lowerQuery($operation, $statement),
            $statement instanceof ParsedInsert => (new InsertLowering())->lower($operation, $statement),
            $statement instanceof ParsedUpdate => (new UpdateLowering())->lower($operation, $statement),
            $statement instanceof ParsedBegin, $statement instanceof ParsedCommit, $statement instanceof ParsedRollback, $statement instanceof ParsedSavepointCommand => (new TransactionLowering())->lower($operation),
            default => (new SelectLowering())->lower($operation),
        };
    }

    /**
     * Publishes a complete value for each statement of a script against the same explicit declaration context.
     *
     * No statement is executed for the ones after it.
     *
     * @return list<Compound|CreateMaterializedView|CreateTable|CreateTableAs|CreateView|Delete|DropIndex|DropTable|DropView|GroupedSelect|Insert|InsertQuery|LocalProjection|RecursiveSeries|RefreshMaterializedView|RenameColumn|RenameTable|Select|Transaction|TruncateTable|Update|ValuesQuery|WithProjection>
     *
     * @throws AnalysisException When a statement is outside the selected grammar
     * @throws SemanticException When a statement has a semantic problem
     * @throws InvalidConstruction When any statement has no complete representation
     */
    public function analyzeAll(string $sql, ?Context $context = null): array
    {
        $declarations = $this->catalog->resolve($context);
        $results = [];
        foreach ($this->split($sql) as $statement) {
            $results[] = $this->analyze($statement, $declarations);
        }

        return $results;
    }

    /**
     * Finds the statement boundaries of a script, as the database finds them.
     *
     * Each text ends with its own terminator; trailing whitespace and comments
     * stay with the last statement. A semicolon inside a string, a comment, or
     * a compound statement ends nothing.
     *
     * @return list<string>
     *
     * @throws AnalysisException When a statement is outside the selected grammar
     */
    public function split(string $sql): array
    {
        try {
            return (new Splitter($this->parser))->split($sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
    }
}
