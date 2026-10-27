<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Notice;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ParseFailure values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure>
 */
final class ParseFailureGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\Diagnostic> $problem Generated constructor operand
     * @param ErisGenerator<bool>|null $aborts Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase>|null $phase Generated constructor operand
     * @param ErisGenerator<bool>|null $endsProgramStatement Generated constructor operand
     */
    public function __construct(ErisGenerator $problem, ?ErisGenerator $aborts = null, ?ErisGenerator $phase = null, ?ErisGenerator $endsProgramStatement = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure {
            return new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure(problem: $parts[0], aborts: $parts[1], phase: $parts[2], endsProgramStatement: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $problem,
            $aborts ?? Generators::constant(false),
            $phase ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax),
            $endsProgramStatement ?? Generators::constant(true)
        ));
    }

    /**
     * Composes completed-production notices through the refused stored statement's boundary.
     * Nothing after its generated stop can contribute to the failure response.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Notice\NameConversion|\SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod, \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure, \SqlSemantics\Platform\MySql\Statement\Notice\ParseStop, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated}>
     */
    public static function completedConditions(): ErisGenerator
    {
        $warnings = \Tests\Generator\Operands::oneOf([
            new NameConversionGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::map(static fn (int $suffix): string => '😀' . $suffix, Generators::choose(0, 100)), Generators::constant(null)), Generators::bool()),
            new HeartbeatPeriodGenerator(new HeartbeatConditionGenerator()),
        ]);
        $failures = new self(
            new \Tests\Generator\MySql\Statement\Expression\Problem\UnknownCollationGenerator(Generators::map(static fn (int $suffix): string => 'absent_collation_' . $suffix, Generators::choose(0, 100))),
            Generators::bool(),
            NoticePhaseGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax, \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization]),
        );
        return \Tests\Generator\Operands::tuple($warnings, $failures, new ParseStopGenerator(), DeprecatedGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BangNot]));
    }

}
