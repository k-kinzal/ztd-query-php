<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Condition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use Tests\Generator\Composition;

/**
 * Composes Condition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Condition>
 */
final class ConditionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Condition> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<M\Routine\Condition\Condition>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = Generators::oneOf(...$alternatives);
    }

    /**
     * Composes invalid literal conditions with their declaration and handler owners.
     *
     * @return ErisGenerator<array{M\Statement, int, string}>
     */
    public static function definitions(GrammarRelease $release, int $owner): ErisGenerator
    {
        return Generators::map(self::definition(...), Generators::tuple(
            Generators::constant($release),
            new self([
                new SqlStateGenerator(new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $value): string => 'bad' . $value, Generators::choose(0, 1000)))),
                new ErrorCodeGenerator(new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::map(static fn (int $value): string => '0.' . $value, Generators::choose(0, 9)))),
            ]),
            new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(),
            Generators::bool(),
            Generators::constant($owner)
        ));
    }

    /**
     * Preserves a generated condition inside each supported stored-program owner.
     *
     * @param array{GrammarRelease, M\Routine\Condition\Condition, \SqlSemantics\Statement\Identifier\Name, bool, int} $parts
     * @return array{M\Statement, int, string}
     * @throws LogicException When the supplied condition is not a literal operand
     */
    public static function definition(array $parts): array
    {
        [$release, $condition, $name, $handler, $owner] = $parts;
        if (!$condition instanceof M\Routine\Condition\SqlState && !$condition instanceof M\Routine\Condition\ErrorCode) {
            throw new LogicException('The generator supplies literal condition operands.');
        }
        $declaration = $handler ? new M\Routine\Program\HandlerDeclaration(M\Routine\Program\HandlerAction::Continue, [$condition], new M\Routine\Program\Block()) : new M\Routine\Program\ConditionDeclaration($name, $condition);
        $body = new M\Routine\Program\Block([$declaration]);
        $qualified = new \SqlSemantics\Statement\Identifier\QualifiedName($name);
        $request = match ($owner) {
            0 => new M\Routine\CreateProcedure($qualified, new M\Routine\ParameterList([]), $body),
            1 => new M\Routine\CreateFunction($qualified, new M\Routine\ParameterList([]), new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Routine\Definition\FunctionImplementation($body)),
            2 => new M\Routine\CreateTrigger($qualified, M\Routine\Trigger\TriggerTime::Before, M\Routine\Trigger\TriggerEvent::Insert, new M\Routine\Trigger\TriggerTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('target'))), $body),
            3 => new M\Routine\CreateEvent($qualified, new M\Routine\Event\OnceSchedule(new M\Literal\StringLiteral(['2030-01-01 00:00:00'])), $body),
            default => new M\Routine\AlterEvent($qualified, body: $body),
        };
        $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('pbt')]);
        return [(new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $request), $condition instanceof M\Routine\Condition\SqlState ? 1407 : 1525, $condition instanceof M\Routine\Condition\SqlState ? "Bad SQLSTATE: '" . $condition->state->value . "'" : "Incorrect CONDITION value: '0'"];
    }
}
