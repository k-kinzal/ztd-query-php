<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Cursor;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes FetchCursor values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor>
 */
final class FetchCursorGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $cursor Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>> $targets Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $from The generated written alternative
     * @param ErisGenerator<bool>|null $next The generated written alternative
     */
    public function __construct(ErisGenerator $cursor, ErisGenerator $targets, ?ErisGenerator $inputSource = null, ?ErisGenerator $from = null, ?ErisGenerator $next = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor(cursor: $parts[0], targets: $parts[1], inputSource: $parts[2], from: $parts[3], next: $parts[4] && $parts[3]);
        }, Generators::tuple(
            $cursor,
            $targets,
            $inputSource ?? Generators::constant(null),
            $from ?? Generators::bool(),
            $next ?? Generators::bool()
        ));
    }

    /**
     * Composes an unbound FETCH and its declaration, with notices before and after it.
     * Names, declaration kind and prefix length shrink together before execution.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, string, list<int>}>
     */
    public static function definitions(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        return Generators::map(static function (array $parts) use ($release): array {
            [$name, $cursor, $target, $kind, $count, $duplicates] = $parts;
            $query = static fn (\SqlSemantics\Platform\MySql\Statement\Query\SelectOption $option): \SqlSemantics\Platform\MySql\Statement\QueryRoot => new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([$option], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]));
            $instructions = [];
            for ($index = 0; $index < $count; ++$index) {
                $instructions[] = $query(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache);
            }
            $instructions[] = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor($cursor, [$target]);
            $instructions[] = $query(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::CalcFoundRows);
            $body = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block(statements: $instructions);
            $qualified = new \SqlSemantics\Statement\Identifier\QualifiedName($name);
            $members = [];
            for ($index = 0; $index <= $duplicates; ++$index) {
                $members[] = new \SqlSemantics\Platform\MySql\Statement\Literal\Text('value');
            }
            $parameters = new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter(new \SqlSemantics\Statement\Identifier\Name('argument'), new \SqlSemantics\Platform\MySql\Statement\Type\Enumeration(\SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind::Enum, $members))]);
            $statement = match ($kind) {
                0 => new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure($qualified, $parameters, $body),
                1 => new \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction($qualified, $parameters, new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int), new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation($body)),
                2 => new \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger($qualified, \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime::Before, \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent::Insert, new \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('target'))), $body),
                3 => new \SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent($qualified, new \SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule(new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['2030-01-01 00:00:00'])), $body),
                default => new \SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent($qualified, body: $body),
            };
            $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('pbt')]);
            return [(new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $statement), 'Undefined CURSOR: ' . $cursor->value, [...array_fill(0, $kind < 2 ? count($members) - 1 : 0, 1291), ...array_fill(0, $release === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? 0 : count($instructions) - 2, 1681), 1324]];
        }, Generators::tuple(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), Generators::choose(0, 4), Generators::choose(0, 3), Generators::choose(0, 2)));
    }
}
