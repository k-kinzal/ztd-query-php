<?php

declare(strict_types=1);

namespace SqlSemantics\Facade;

use SqlSemantics\Core\Analysis\Analyzer;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Semantic\Schema\Table;
use SqlSemantics\Semantic\Statement\Delete;
use SqlSemantics\Semantic\Statement\InsertRows;
use SqlSemantics\Semantic\Statement\InsertSelect;
use SqlSemantics\Semantic\Statement\Select;

/**
 * Structures SQL meaning, optionally resolving references against a closed catalog.
 *
 * @visibility public
 * @example Analyzing a query without declarations
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $semantics->analyze('SELECT foo FROM bar')->field('foo')->type->name // => 'unknown'
 */
final class Semantics
{
    private readonly Analyzer $analyzer;

    /**
     * Selects the SQL dialect and its parser release.
     */
    public function __construct(Dialect $dialect, ?string $grammarVersion = null)
    {
        $this->analyzer = new Analyzer($dialect, $grammarVersion);
    }

    /**
     * @param list<Table>|null $tables Null leaves declarations unknown; [] supplies a closed empty catalog.
     * @throws \SqlSemantics\Core\AnalysisException When SQL is not in the selected language
     */
    public function analyze(string $sql, ?array $tables = null): Select|InsertRows|InsertSelect|Delete
    {
        return $this->analyzer->analyze($sql, $tables);
    }
}
