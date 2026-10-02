<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Lexer\SourceException;
use SqlSemantics\Core\AnalysisException;
use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\Declarations;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Policy\OperationRules;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\SemanticGraph;

/**
 * Resolves the meaning of SQL against the exact supplied declaration objects.
 * @visibility SqlSemantics
 */
final class Analyzer
{
    private readonly DialectParser $parser;
    private readonly OperationRules $operations;

    /**
     * @param non-empty-list<string> $path Lookup namespaces in precedence order
     */
    public function __construct(private readonly Language $language, private readonly array $path)
    {
        $this->parser = new DialectParser($language);
        $this->operations = $language->dialect->platform()->operations($language);
    }

    /**
     * Builds an operation whose references retain supplied declarations without executing them.
     * @param list<Table|Operation>|Catalog|null $dependencies Null means declaration metadata is absent
     * @throws AnalysisException When SQL does not match the selected grammar
     */
    public function analyze(string $sql, array|Catalog|null $dependencies = null, Declarations $declarations = Declarations::Complete): Operation
    {
        try {
            $tree = $this->parser->parse($sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
        $catalog = $this->context($dependencies, $declarations);
        $operation = $this->operations->read($tree, $catalog);
        \SqlSemantics\Statement\Validation\Check::invariant((new SemanticGraph())->isSemanticOperation($operation), 'Analysis returns immutable semantic values without parser or grammar models.');
        return $operation;
    }

    /**
     * Each statement sees the same explicit context, irrespective of preceding requests.
     * @param list<Table|Operation>|Catalog|null $dependencies
     * @return list<Operation>
     * @throws AnalysisException When SQL does not match the selected grammar
     */
    public function analyzeAll(string $sql, array|Catalog|null $dependencies = null, Declarations $declarations = Declarations::Complete): array
    {
        $context = $this->context($dependencies, $declarations);
        return array_map(fn (string $text): Operation => $this->analyze($text, $context), $this->split($sql));
    }

    /**
     * Constructs a fresh declaration snapshot or validates an explicitly supplied one.
     * @param list<Table|Operation>|Catalog|null $dependencies
     */
    public function context(array|Catalog|null $dependencies = null, Declarations $declarations = Declarations::Complete): Catalog
    {
        if ($dependencies instanceof Catalog) {
            \SqlSemantics\Statement\Validation\Check::input($this->language->profile()->compatibleWith($dependencies->profile), 'The context must match the selected language profile.');
            return $dependencies;
        }
        $tables = (new CatalogReader())->tables(...($dependencies ?? []));
        return $this->language->dialect->platform()->catalog($this->language, $this->path, $dependencies !== null && $declarations === Declarations::Complete, ...$tables);
    }

    /**
     * Finds script boundaries without executing statements or interpreting declaration history.
     * @return list<string>
     * @throws AnalysisException When SQL does not match the selected grammar
     */
    public function split(string $sql): array
    {
        try {
            return $this->parser->split($sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
    }
}
