<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use InvalidArgumentException;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\SqlParser;
use SqlSemantics\AnalysisContext as Context;
use SqlSemantics\Analyzer;
use SqlSemantics\Contract\Mode;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupedLowering;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\MySql\Ir\Mutation\Delete;
use SqlSemantics\Platform\MySql\Ir\Mutation\DeleteLowering;
use SqlSemantics\Platform\MySql\Ir\Mutation\Insert;
use SqlSemantics\Platform\MySql\Ir\Mutation\InsertLowering;
use SqlSemantics\Platform\MySql\Ir\Mutation\InsertQuery;
use SqlSemantics\Platform\MySql\Ir\Mutation\Update;
use SqlSemantics\Platform\MySql\Ir\Mutation\UpdateLowering;
use SqlSemantics\Platform\MySql\Ir\Rows\Compound;
use SqlSemantics\Platform\MySql\Ir\Rows\CompoundLowering;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjectionLowering;
use SqlSemantics\Platform\MySql\Ir\Rows\Recursive\RecursiveSeriesLowering;
use SqlSemantics\Platform\MySql\Ir\Rows\RecursiveSeries;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithLowering;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\MySql\Ir\Schema\Alter\RelationChangeLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Alter\RenameTable;
use SqlSemantics\Platform\MySql\Ir\Schema\CreateRelationLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\MySql\Ir\Schema\DropTable;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\AddForeignKeys;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\DropForeignKeys;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\AlterIndexes;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\CreateIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\CreateIndexLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexes;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\RenameIndexes;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SetIndexVisibility;
use SqlSemantics\Platform\MySql\Ir\Schema\Like\CreateTableLike;
use SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateTableAs;
use SqlSemantics\Platform\MySql\Ir\Schema\Truncate\TruncateTable;
use SqlSemantics\Platform\MySql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\MySql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Ir\Transaction\Transaction;
use SqlSemantics\Platform\MySql\Ir\Transaction\TransactionLowering;
use SqlSemantics\Platform\MySql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Model\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Diagnostic\SemanticException;
use SqlSemantics\Platform\MySql\Model\Facade\Catalog;
use SqlSemantics\Platform\MySql\Model\Facade\Splitter;
use SqlSemantics\Platform\MySql\Model\Facade\StatementBinder;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete as ParsedDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery as ParsedInsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows as ParsedInsert;
use SqlSemantics\Platform\MySql\Statement\Dml\Update as ParsedUpdate;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery as ParsedParenthesized;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression as ParsedWith;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation as ParsedCompound;
use SqlSemantics\Platform\MySql\Statement\Query\With\With as ParsedRecursiveWith;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable as ParsedDerived;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin as ParsedBegin;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit as ParsedCommit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\ReleaseSavepoint as ParsedReleaseSavepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Rollback as ParsedRollback;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\RollbackToSavepoint as ParsedRollbackToSavepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint as ParsedSavepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\StartTransaction as ParsedStartTransaction;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex as ParsedCreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike as ParsedCreateTableLike;
use SqlSemantics\Platform\MySql\Statement\View\CreateView as ParsedCreateView;

/**
 * Binds SQL under a fixed dialect profile and publishes complete semantic values.
 *
 * @visibility public
 * @example Analyzing and rendering a projection
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
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
    public function analyze(string $sql, ?Context $context = null): AddForeignKeys|Compound|CreateIndex|CreateTable|CreateTableAs|CreateTableLike|CreateView|Delete|DropForeignKeys|DropIndex|DropIndexes|DropTable|AlterIndexes|RenameIndexes|DropView|GroupedSelect|Insert|InsertQuery|LocalProjection|RecursiveSeries|RenameTable|Select|SetIndexVisibility|Transaction|TruncateTable|Update|WithProjection
    {
        $declarations = $this->catalog->resolve($context);
        $operation = $this->binder->resolve($sql, $declarations);
        $statement = $operation->statement;
        $relationChange = new RelationChangeLowering();

        return match (true) {
            $statement instanceof ParsedWith && $statement->with instanceof ParsedRecursiveWith && $statement->with->recursive => (new RecursiveSeriesLowering())->lower($operation, $statement),
            $statement instanceof ParsedWith && $statement->with !== null => (new WithLowering())->lower($operation, $statement),
            $statement instanceof ParsedSelect && $statement->from instanceof ParsedDerived => (new LocalProjectionLowering())->lower($operation),
            $statement instanceof ParsedCompound, $statement instanceof ParsedParenthesized => (new CompoundLowering())->lower($operation, $statement),
            $statement instanceof ParsedSelect && (new GroupedLowering())->applies($statement) => (new GroupedLowering())->lower($operation, $statement),
            $statement instanceof ParsedDelete => (new DeleteLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateIndex => (new CreateIndexLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateTable, $statement instanceof ParsedCreateTableLike, $statement instanceof ParsedCreateView => (new CreateRelationLowering())->lower($operation, $statement),
            $relationChange->applies($statement) => $relationChange->lower($operation, $statement),
            $statement instanceof ParsedInsertQuery => (new InsertLowering())->lowerQuery($operation, $statement),
            $statement instanceof ParsedInsert => (new InsertLowering())->lower($operation, $statement),
            $statement instanceof ParsedUpdate => (new UpdateLowering())->lower($operation, $statement),
            $statement instanceof ParsedBegin, $statement instanceof ParsedCommit, $statement instanceof ParsedRollback, $statement instanceof ParsedStartTransaction, $statement instanceof ParsedSavepoint, $statement instanceof ParsedReleaseSavepoint, $statement instanceof ParsedRollbackToSavepoint => (new TransactionLowering())->lower($operation),
            default => (new SelectLowering())->lower($operation),
        };
    }

    /**
     * Publishes a complete value for each statement of a script against the same explicit declaration context.
     *
     * No statement is executed for the ones after it.
     *
     * @return list<AddForeignKeys|Compound|CreateIndex|CreateTable|CreateTableAs|CreateTableLike|CreateView|Delete|DropForeignKeys|DropIndex|DropIndexes|DropTable|AlterIndexes|RenameIndexes|DropView|GroupedSelect|Insert|InsertQuery|LocalProjection|RecursiveSeries|RenameTable|Select|SetIndexVisibility|Transaction|TruncateTable|Update|WithProjection>
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
