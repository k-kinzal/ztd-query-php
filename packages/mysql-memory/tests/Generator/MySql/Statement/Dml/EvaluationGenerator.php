<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use SqlSemantics\Platform\MySql\Statement as M;
use Tests\Generator\Composition;

/**
 * Composes Evaluation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Evaluation>
 */
final class EvaluationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Evaluation> */
    use Composition;

    /**
     * Generates complete DO requests whose fixed condition follows a dependent table lookup.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function dependent(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        return Generators::bind(\Tests\Generator\MySql\Statement\Table\Key\IndexDefinitionGenerator::tables($release), static function (\SqlSemantics\Statement\AnalyzedStatement $definition) use ($release): ErisGenerator {
            \SqlSemantics\Diagnostic\Check::invariant($definition instanceof M\Table\CreateTable, 'A dependent DO names its generated table.');
            $numbers = new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 100)));
            $rows = new \Tests\Generator\MySql\Statement\Routine\Program\DeferredProjectionGenerator(new \Tests\Generator\MySql\Statement\Expression\RowGenerator(Generators::vector(2, $numbers)));
            $conditions = $release === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? $rows : Generators::oneOf(...[$rows, new \Tests\Generator\MySql\Statement\Query\StarGenerator()]);
            $items = Generators::map(static function (M\Routine\Program\DeferredProjection|M\Query\Star $condition) use ($definition): array {
                $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], new M\Relation\TableReference($definition->name));
                return [$condition, new M\Query\SelectExpression(new M\Expression\Subquery\ScalarSubquery($query))];
            }, $conditions);
            $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release->value);
            $search = new \SqlSemantics\Contract\SearchPath($definition->name->schema->value ?? 'pbt');
            $absent = $semantics->context([], true, $search);
            $present = $semantics->context($definition->declarations(), true, $search);
            return Generators::bind(self::operands($items), static fn (M\Dml\Evaluation $request): ErisGenerator => Generators::tuple(
                new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, Generators::constant($request), Generators::constant($absent)),
                Generators::constant($definition),
                new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, Generators::constant($request), Generators::constant($present)),
                Generators::constant($request->items[0] instanceof M\Query\Star ? 1096 : 1241),
            ));
        });
    }

    /**
     * Generates complete Evaluation values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<M\Dml\Evaluation>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            $forms[] = self::operands(Generators::vector(1, new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(\Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::nat()))]))));
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): M\Dml\Evaluation {
            if (!$statement instanceof M\Dml\Evaluation) {
                throw new LogicException('Publishing Evaluation must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<list<M\Query\SelectExpression|M\Routine\Program\DeferredProjection|M\Query\Star|M\Name\TableWildcard>> $items Generated constructor operand
     * @param ErisGenerator<M\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<M\Dml\Evaluation>
     */
    public static function operands(ErisGenerator $items, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): M\Dml\Evaluation {
            return new M\Dml\Evaluation(items: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple($items, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
}
