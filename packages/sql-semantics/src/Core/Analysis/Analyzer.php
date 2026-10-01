<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Lexer\SourceException;
use SqlSemantics\Core\AnalysisException;
use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\StatementList;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Semantic\Schema\Table;
use SqlSemantics\Semantic\Statement\Delete;
use SqlSemantics\Semantic\Statement\InsertRows;
use SqlSemantics\Semantic\Statement\InsertSelect;
use SqlSemantics\Semantic\Statement\Select;

/**
 * Lowers syntax into operations and derives facts in one analysis.
 * @visibility SqlSemantics
 */
final class Analyzer
{
    private readonly DialectParser $parser;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(private readonly Dialect $dialect, ?string $grammarVersion = null)
    {
        $this->parser = new DialectParser($dialect, $grammarVersion);
    }

    /**
     * @param list<Table>|null $tables Null means no catalog; an empty array is a closed empty catalog.
     * @throws AnalysisException
     */
    public function analyze(string $sql, ?array $tables = null): Select|InsertRows|InsertSelect|Delete
    {
        try {
            $tree = $this->parser->parse($sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
        $statements = StatementList::read($tree, $this->dialect, $this->parser->version());
        if (count($statements) !== 1) {
            Tree::unsupported($tree, 'multiple statements');
        }
        $statement = $statements[0];
        $catalog = new Catalog(new Identifiers($this->dialect), $tables);
        if (Tree::outer($statement, $this->dialect->platform()->syntax()->nodes('insertStatement')) !== []) {
            return $this->dialect->platform()->inserts()->read($statement, new InsertReader($catalog));
        }
        if (strtoupper($statement->tokens()[0]->text) === 'DELETE') {
            return (new DeleteReader($catalog))->read($statement);
        }
        Tree::assertChildren($statement, $this->dialect->platform()->syntax()->nodes('selectStatement'), []);
        return (new SelectReader($catalog))->read($statement);
    }
}
