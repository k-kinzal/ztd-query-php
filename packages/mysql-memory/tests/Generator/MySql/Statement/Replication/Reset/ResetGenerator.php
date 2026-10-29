<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Reset;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete Reset values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }
            foreach (self::additionalForms() as [$grammar, $form]) {
                if ($grammar === $release) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No Reset forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset) {
                throw new LogicException('Publishing Reset must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetTarget>> $targets Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset>
     */
    public static function operands(ErisGenerator $targets, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset(targets: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple($targets, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset>>
     */
    public static function forms(): iterable
    {
        yield 'Reset/372' => ResetGenerator::operands(
            targets: Generators::tuple(
                new ResetBinaryLogsGenerator(
                    terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
                    first: Generators::constant(null),
                    synonym: Generators::constant(false)
                )
            )
        );
        yield 'Reset/373' => ResetGenerator::operands(
            targets: Generators::tuple(
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
        yield 'Reset/additional/72' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, ResetGenerator::operands(
            targets: Generators::tuple(
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
