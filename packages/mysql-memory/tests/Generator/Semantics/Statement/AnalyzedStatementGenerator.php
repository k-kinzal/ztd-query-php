<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\AnalyzedStatement;
use Tests\Generator\Composition;
use Tests\Generator\Semantics\Contract\AnalysisContextGenerator;

/**
 * Builds complete concrete statements from generated operands and declarations.
 * The model binds those operands directly; no SQL renderer or parser participates.
 *
 * @implements ErisGenerator<AnalyzedStatement>
 */
final class AnalyzedStatementGenerator implements ErisGenerator
{
    /** @use Composition<Statement> */
    use Composition;

    /**
     * @param ErisGenerator<Statement> $statements The generated SQL forms
     * @param ErisGenerator<AnalysisContext>|null $contexts Related declaration and parameter inputs
     */
    public function __construct(GrammarRelease $release, ErisGenerator $statements, ?ErisGenerator $contexts = null)
    {
        $contexts ??= AnalysisContextGenerator::mysql($release);
        $origins = new Source\OriginGenerator(
            new NodeGenerator([new StatementGenerator([$statements])]),
            Generators::choose(0, 128),
            Generators::choose(0, 64),
        );
        $sources = new Source\SourceMapGenerator(Generators::vector(1, $origins));
        $this->delegate = Generators::map(static function (array $parts): Statement {
            [$context, $sources] = $parts;
            $statement = $sources->origins[0]->node;
            if (!$statement instanceof Statement) {
                throw new LogicException('The generated source origin belongs to its MySQL statement.');
            }
            return (new Publication())->publish($context, $statement, $sources);
        }, \Tests\Generator\Operands::tuple($contexts, $sources));
    }
}
