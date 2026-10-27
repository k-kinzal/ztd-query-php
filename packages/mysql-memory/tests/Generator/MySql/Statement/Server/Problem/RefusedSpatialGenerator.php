<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedSpatial values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedSpatial>
 */
final class RefusedSpatialGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedSpatial> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialCommand> $command Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem> $problem Generated constructor operand
     */
    public function __construct(ErisGenerator $command, ErisGenerator $problem)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedSpatial {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedSpatial(command: $parts[0], problem: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $command,
            $problem
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedSpatial>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Server\\Problem\\RefusedSpatial' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedSpatialGenerator(
            command: \Tests\Generator\MySql\Statement\Server\Spatial\SpatialCommandGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialCommand::Drop]),
            problem: new SpatialProblemGenerator(
                rule: SpatialRuleGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::IdentifierOutOfRange]),
                attribute: Generators::constant(null),
                value: Generators::constant(null)
            )
        )];
    }

}
