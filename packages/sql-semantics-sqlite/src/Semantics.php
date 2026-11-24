<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use InvalidArgumentException;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\SqlParser;
use SqlSemantics\AnalysisContext as Context;
use SqlSemantics\Analyzer;
use SqlSemantics\Contract\Mode;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupedLowering;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\Sqlite\Ir\Connection\Attach;
use SqlSemantics\Platform\Sqlite\Ir\Connection\ConnectionLowering;
use SqlSemantics\Platform\Sqlite\Ir\Connection\Detach;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Analyze;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSize;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSpill;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\DefaultCacheSize;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityAudit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\MaintenanceLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\ForeignKeyAudit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableListing;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PagePragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\AutoVacuum;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\IncrementalVacuum;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reindex;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\Vacuum;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\DeleteLowering;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Insert;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertLowering;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateLowering;
use SqlSemantics\Platform\Sqlite\Ir\Rows\Compound;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundLowering;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesLowering;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddColumnLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddGeneratedColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\DropColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\DropColumnLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\RenameColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\RenameLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\RenameTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DefinitionLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropView;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\CreateIndex;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreateTableAs;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\SelectLowering;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\Transaction;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\TransactionLowering;
use SqlSemantics\Platform\Sqlite\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\AnalysisException;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\SemanticException;
use SqlSemantics\Platform\Sqlite\Model\Facade\Catalog;
use SqlSemantics\Platform\Sqlite\Model\Facade\Splitter;
use SqlSemantics\Platform\Sqlite\Model\Facade\StatementBinder;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Attach as ParsedAttach;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Detach as ParsedDetach;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Analyze as ParsedAnalyze;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma as ParsedPragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Reindex as ParsedReindex;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Vacuum as ParsedVacuum;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete as ParsedDelete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertDefaults as ParsedInsertDefaults;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows as ParsedInsert;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect as ParsedInsertSelect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update as ParsedUpdate;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound as ParsedCompound;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterAddColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterDropColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterRenameColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterRenameTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateIndex as ParsedCreateIndex;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTableAs as ParsedCreateTableAs;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateView as ParsedCreateView;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Drop;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger as ParsedCreateTrigger;

/**
 * Binds SQL under a fixed dialect profile and publishes complete semantic values.
 *
 * @visibility public
 * @example Analyzing and rendering a projection
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
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
     * Builds a context from explicit relation and index definitions.
     *
     * Null describes an open catalog. An explicit list can enumerate the entire catalog.
     * @param array<array-key, mixed>|null $declarations An ordered list of relation and index definitions
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
    public function analyze(string $sql, ?Context $context = null): Select|GroupedSelect|Compound|Delete|Insert|Update|ValuesQuery|DropTable|DropView|CreateTable|CreateTableAs|CreateTrigger|CreateView|RenameColumn|RenameTable|AddColumn|AddGeneratedColumn|DropColumn|CreateIndex|DropIndex|Reindex|Analyze|FlagPragma|InventoryPragma|HeaderPragma|IntegrityAudit|ForeignKeyAudit|IndexInspection|TableInspection|TableListing|PagePragma|AutoVacuum|IncrementalVacuum|CacheSize|DefaultCacheSize|CacheSpill|Vacuum|Attach|Detach|Transaction
    {
        $declarations = $this->catalog->resolve($context);
        $operation = $this->binder->resolve($sql, $declarations);
        $statement = $operation->statement;

        return match (true) {
            $statement instanceof ParsedSelect && (new GroupedLowering())->applies($statement) => (new GroupedLowering())->lower($operation, $statement),
            $statement instanceof ParsedSelect, $statement instanceof WithQuery => (new SelectLowering())->lower($operation),
            $statement instanceof ParsedCompound => (new CompoundLowering())->lower($operation, $statement),
            $statement instanceof ParsedDelete => (new DeleteLowering())->lower($operation, $statement),
            $statement instanceof ParsedInsert, $statement instanceof ParsedInsertSelect, $statement instanceof ParsedInsertDefaults => (new InsertLowering())->lower($operation, $statement),
            $statement instanceof ParsedUpdate => (new UpdateLowering())->lower($operation, $statement),
            $statement instanceof ValuesClause => (new ValuesLowering())->lower($operation, $statement),
            $statement instanceof Drop => (new DropLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateTable, $statement instanceof ParsedCreateIndex, $statement instanceof ParsedCreateTableAs,
            $statement instanceof ParsedCreateTrigger, $statement instanceof ParsedCreateView => (new DefinitionLowering())->lower($operation, $statement),
            $statement instanceof AlterRenameColumn, $statement instanceof AlterRenameTable => (new RenameLowering())->lower($operation, $statement),
            $statement instanceof AlterAddColumn => (new AddColumnLowering())->lower($operation, $statement),
            $statement instanceof AlterDropColumn => (new DropColumnLowering())->lower($operation, $statement),
            $statement instanceof ParsedAnalyze, $statement instanceof ParsedReindex, $statement instanceof ParsedPragma, $statement instanceof ParsedVacuum => (new MaintenanceLowering())->lower($operation, $statement),
            $statement instanceof ParsedAttach, $statement instanceof ParsedDetach => (new ConnectionLowering())->lower($operation, $statement),
            default => (new TransactionLowering())->lower($statement),
        };
    }

    /**
     * Publishes a complete value for each statement of a script against the same explicit declaration context.
     *
     * No statement is executed for the ones after it.
     *
     * @return list<Select|GroupedSelect|Compound|Delete|Insert|Update|ValuesQuery|DropTable|DropView|CreateTable|CreateTableAs|CreateTrigger|CreateView|RenameColumn|RenameTable|AddColumn|AddGeneratedColumn|DropColumn|CreateIndex|DropIndex|Reindex|Analyze|FlagPragma|InventoryPragma|HeaderPragma|IntegrityAudit|ForeignKeyAudit|IndexInspection|TableInspection|TableListing|PagePragma|AutoVacuum|IncrementalVacuum|CacheSize|DefaultCacheSize|CacheSpill|Vacuum|Attach|Detach|Transaction>
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
