<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Facade;

use SqlParser\Lexer\SourceException;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\SemanticException;
use SqlSemantics\Platform\MySql\Model\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Model\Validation\LeafEmbedding;
use SqlSemantics\Platform\MySql\Model\Validation\ValueGraph;
use SqlSemantics\Platform\MySql\Platform;

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
        $graph = new ValueGraph(['SqlSemantics\\Platform\\MySql\\Model\\Statement\\', 'SqlSemantics\\Platform\\MySql\\Model\\Contract\\', $platform->statementNamespace()]);
        $dropped = (new LeafEmbedding())->dropped($leaves, $graph->objects($statement));
        Check::invariant($dropped === null, 'The statement lost an operand of the input: ' . ($dropped === null ? '' : $dropped::class));

        $binding = new BoundStatement($context, $statement);
        $errors = $binding->facts->diagnostics;
        if ($errors !== []) {
            throw new SemanticException($errors);
        }

        return $binding;
    }
}
