<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Construction;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Source\SourceMap;
use SqlSemantics\Statement\Statement;

/**
 * Publishes concrete statements with information attached to each SQL occurrence.
 *
 * The declaration context and derivation indexes exist only while constructing
 * this result. Consumers receive neither of them and never need source SQL.
 *
 * @visibility public
 * @example Constructing an executable statement from typed SQL operands
 *     $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
 *     $analysis = new \SqlSemantics\Contract\AnalysisContext($profile, [new \SqlSemantics\Statement\Identifier\Name('test')]);
 *     (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($analysis, new \SqlSemantics\Platform\MySql\Statement\Server\Instance\Restart())->toString() // => 'RESTART'
 */
final class Publication
{
    /**
     * Binds a fresh structure and returns its concrete statement type.
     */
    public function publish(AnalysisContext $context, Statement $statement, SourceMap $sources = new SourceMap()): \SqlSemantics\Platform\MySql\Statement\Statement
    {
        if ($statement instanceof \SqlSemantics\Statement\Script) {
            Check::input($statement->statements === [], 'A MySQL analysis contains one statement or empty input.');
            $statement = new \SqlSemantics\Platform\MySql\Statement\EmptyStatement();
        }
        if ($statement instanceof \SqlSemantics\Statement\AnalyzedStatement) {
            $sources = $sources->retainingInputNotices($statement);
        }
        $occurrences = new Occurrences(sources: $sources);
        $result = $occurrences->copy($statement);
        Check::input($result instanceof \SqlSemantics\Platform\MySql\Statement\Statement, 'A MySQL analysis result is a concrete statement.');
        [$facts, $statements] = (new \SqlSemantics\Validation\Publication())->derive($context, $result, $occurrences->sources());
        $facts->localize();
        foreach ($statements as [$node, $environment, $information]) {
            Check::invariant($node instanceof \SqlSemantics\Platform\MySql\Statement\Statement, 'Nested statements belong to the MySQL language.');
            $information = $node === $result ? $facts : $information;
            $node->initializeStatementInformation((new StatementResults())->statement($environment, $information));
        }
        return $result;
    }
}
