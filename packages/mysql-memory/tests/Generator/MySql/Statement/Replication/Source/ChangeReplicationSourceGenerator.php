<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Source;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ChangeReplicationSource values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource>
 */
final class ChangeReplicationSourceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Terminology> $terminology Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption>> $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $channel Generated constructor operand
     * @param ErisGenerator<bool>|null $synonym Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $terminology, ErisGenerator $options, ?ErisGenerator $channel = null, ?ErisGenerator $synonym = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource(terminology: $parts[0], options: $parts[1], channel: $parts[2], synonym: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $terminology,
            $options,
            $channel ?? Generators::constant(null),
            $synonym ?? Generators::constant(false),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource>>
     */
    public static function forms(): iterable
    {
        yield 'ChangeReplicationSource/17' => new ChangeReplicationSourceGenerator(
            terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
            options: \Tests\Generator\Operands::tuple(
                new SourceOptionGenerator(
                    terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
                    kind: SourceOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::Port]),
                    value: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                        text: Generators::constant('1'),
                        hexadecimal: Generators::constant(false)
                    ),
                    synonym: Generators::constant(false)
                )
            ),
            channel: Generators::constant(null),
            synonym: Generators::constant(false)
        );
        yield 'ChangeReplicationSource/133' => new ChangeReplicationSourceGenerator(
            terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
            options: \Tests\Generator\Operands::tuple(
                new SourceOptionGenerator(
                    terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
                    kind: SourceOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::IgnoreServerIds]),
                    value: new ServerIdsGenerator(
                        ids: Generators::constant([])
                    ),
                    synonym: Generators::constant(false)
                )
            ),
            channel: Generators::constant(null),
            synonym: Generators::constant(false)
        );
        yield 'ChangeReplicationSource/134' => new ChangeReplicationSourceGenerator(
            terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
            options: \Tests\Generator\Operands::tuple(
                new SourceOptionGenerator(
                    terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
                    kind: SourceOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::RequireTablePrimaryKeyCheck]),
                    value: PrimaryKeyCheckGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Source\PrimaryKeyCheck::Stream]),
                    synonym: Generators::constant(false)
                )
            ),
            channel: Generators::constant(null),
            synonym: Generators::constant(false)
        );
        yield 'ChangeReplicationSource/135' => new ChangeReplicationSourceGenerator(
            terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
            options: \Tests\Generator\Operands::tuple(
                new SourceOptionGenerator(
                    terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
                    kind: SourceOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::AssignGtidsToAnonymousTransactions]),
                    value: AnonymousGtidsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Source\AnonymousGtids::Off]),
                    synonym: Generators::constant(false)
                )
            ),
            channel: Generators::constant(null),
            synonym: Generators::constant(false)
        );
    }

}
