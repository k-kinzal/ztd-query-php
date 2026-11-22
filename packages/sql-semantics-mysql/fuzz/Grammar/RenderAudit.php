<?php

declare(strict_types=1);

namespace SqlSemantics\Testing\MySql;

use SqlParser\Lexer\SourceException;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvariantViolation;
use SqlSemantics\Platform\MySql\Model\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\Facts;
use SqlSemantics\Platform\MySql\Model\Statement\Script;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Model\Validation\Equivalence;
use SqlSemantics\Platform\MySql\Model\Validation\Publication;
use SqlSemantics\Platform\MySql\Platform;

/**
 * Runs rendering correspondence checks for the grammar fixtures.
 */
final class RenderAudit
{
    /**
     * Derives facts and checks the rendered grammar fixture.
     * @return array{Facts, string}
     * @throws InvariantViolation When the fixture does not round trip
     */
    public function establish(AnalysisContext $context, Statement $statement): array
    {
        $platform = Platform::shared();
        $facts = (new Publication())->facts($context, $statement);
        $output = new Output($platform->codec($context->profile));
        $statement->render($output);
        $sql = (new Lexical())->join($output->pieces()) . $output->trailing();
        (new Spellings($platform->parser($context->profile), $platform->productions($context->profile), $platform->leafKeys($context->profile)))->check((new Lexical())->join($output->canonical()), $sql);
        try {
            $tree = $platform->parser($context->profile)->parse($sql);
        } catch (SourceException $error) {
            throw new InvariantViolation('The rendered SQL is outside the grammar: ' . $error->getMessage() . ' in: ' . $sql, 0, $error);
        }
        $difference = (new Equivalence())->difference($statement, $this->root($platform->lower($tree, $context->profile, new Leaves())));
        Check::invariant($difference === null, 'The rendered SQL does not correspond to the statement at ' . $difference . ' in: ' . $sql);

        return [$facts, $sql];
    }

    /**
     * Answers the root of a lowered input: its only statement, or a script of none or several.
     *
     * @param list<Statement> $statements
     */
    public function root(array $statements): Statement
    {
        return count($statements) === 1 ? $statements[0] : new Script($statements);
    }
}
