<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Facade;

use SqlParser\Lexer\SourceException;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Platform\PostgreSql\LocalCatalogBinding;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\SemanticException;
use SqlSemantics\Platform\PostgreSql\Model\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\WarningDiagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Validation\LeafEmbedding;
use SqlSemantics\Platform\PostgreSql\Model\Validation\ValueGraph;
use SqlSemantics\Platform\PostgreSql\Platform;

/**
 * Parses a statement once and binds it against an explicit catalog.
 *
 * @visibility SqlSemantics
 */
final class StatementBinder
{
    /**
     * Fixes the parser for the dialect language profile.
     */
    public function __construct(private readonly SqlParser $parser)
    {
    }

    /**
     * Checks the complete input structure and resolves its static references.
     * @throws AnalysisException When the SQL is outside the grammar
     * @throws SemanticException When binding finds a semantic error
     */
    public function resolve(string $sql, AnalysisContext $context): BoundStatement
    {
        try {
            $tree = $this->parser->parse($sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
        $platform = Platform::shared();
        $leaves = new Leaves();
        $statements = $platform->lower($tree, $context->profile, $leaves);
        Check::input(count($statements) === 1, 'A complete IR requires exactly one SQL statement.');
        $statement = $statements[0];
        $graph = new ValueGraph(['SqlSemantics\\Platform\\PostgreSql\\Model\\Statement\\', 'SqlSemantics\\Platform\\PostgreSql\\Model\\Contract\\', $platform->statementNamespace()]);
        $objects = $graph->objects($statement);
        $dropped = (new LeafEmbedding())->dropped($leaves, $objects);
        Check::invariant($dropped === null, 'The statement lost an operand of the input: ' . ($dropped === null ? '' : $dropped::class));
        (new LocalCatalogBinding())->check($objects, $context->currentDatabase);

        $binding = new BoundStatement($context, $statement);
        $errors = array_values(array_filter(
            $binding->facts->diagnostics,
            static fn (Diagnostic $problem): bool => !($problem instanceof WarningDiagnostic && $problem->warning()),
        ));
        if ($errors !== []) {
            throw new SemanticException($errors);
        }

        return $binding;
    }
}
