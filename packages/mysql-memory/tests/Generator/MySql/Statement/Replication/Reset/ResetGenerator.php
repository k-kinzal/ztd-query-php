<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Reset;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Reset values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset>
 */
final class ResetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetTarget>> $targets Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $targets, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset(targets: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $targets,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset>>
     */
    public static function forms(): iterable
    {
        yield 'Reset/372' => new ResetGenerator(
            targets: \Tests\Generator\Operands::tuple(
                new ResetBinaryLogsGenerator(
                    terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
                    first: Generators::constant(null),
                    synonym: Generators::constant(false)
                )
            )
        );
        yield 'Reset/373' => new ResetGenerator(
            targets: \Tests\Generator\Operands::tuple(
                new ResetBinaryLogsGenerator(
                    terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
                    first: Generators::constant(null),
                    synonym: Generators::constant(false)
                ),
                new ResetReplicaGenerator(
                    terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
                    all: Generators::constant(false),
                    channel: Generators::constant(null),
                    synonym: Generators::constant(false)
                )
            )
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'Reset/additional/72' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, new ResetGenerator(
            targets: \Tests\Generator\Operands::tuple(
                new ResetQueryCacheGenerator(),
                new ResetBinaryLogsGenerator(
                    terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Legacy]),
                    first: Generators::constant(null),
                    synonym: Generators::constant(false)
                )
            )
        )];
    }

}
