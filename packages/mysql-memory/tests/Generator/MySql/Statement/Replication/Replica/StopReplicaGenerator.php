<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Replica;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes StopReplica values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica>
 */
final class StopReplicaGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica> */
    use Composition;

    /**
     * Generates complete StopReplica values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if (str_starts_with($release->value, 'mysql-')) {
                foreach (self::forms($release) as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No StopReplica forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica) {
                throw new LogicException('Publishing StopReplica must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Terminology> $terminology Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaThread>>|null $threads Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $channel Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica>
     */
    public static function operands(ErisGenerator $terminology, ?ErisGenerator $threads = null, ?ErisGenerator $channel = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica(terminology: $parts[0], threads: $parts[1], channel: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, Generators::tuple($terminology, $threads ?? Generators::constant([]), $channel ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Generates thread lists and optional channel names in the release's vocabulary.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica>>
     */
    public static function forms(\SqlSemantics\Contract\GrammarRelease $release = \SqlSemantics\Contract\GrammarRelease::MySql847): iterable
    {
        $rules = new \SqlSemantics\Platform\MySql\Rules\Replication\Releases();
        $terminology = [$rules->source($release)];
        if ($terminology[0] === \SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current && $rules->replica($release, \SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Legacy)) {
            $terminology[] = \SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Legacy;
        }
        $channel = $release === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::elements('', 'channel', 'missing')));
        yield 'thread lists and channels' => self::operands(Generators::elements($terminology), Generators::seq(new ReplicaThreadGenerator()), $channel);
    }

    /**
     * Composes repository setup, channel initialization, local applier startup and stopping. Bind keeps
     * every statement on the same generated channel while shrinking its name.
     * The server must start with a nonzero server_id on legacy releases.
     *
     * @return ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Statement>>
     */
    public static function withPrerequisites(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $channels = $release === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $id): string => 'channel_' . $id, Generators::choose(0, 20))));
        return Generators::bind($channels, static function (?\SqlSemantics\Platform\MySql\Statement\Literal\Text $name) use ($release): ErisGenerator {
            $channel = Generators::constant($name);
            $words = Generators::constant((new \SqlSemantics\Platform\MySql\Rules\Replication\Releases())->source($release));
            $option = new \Tests\Generator\MySql\Statement\Replication\Source\SourceOptionGenerator($words, Generators::constant(\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::ConnectRetry), new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::map(static fn (int $retry): string => (string) $retry, Generators::choose(1, 100))));
            $host = new \Tests\Generator\MySql\Statement\Replication\Source\SourceOptionGenerator($words, Generators::constant(\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::Host), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $id): string => 'source-' . $id . '.invalid', Generators::choose(0, 20))));
            $prerequisites = [];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql5744) {
                $assignments = [];
                foreach (['master_info_repository', 'relay_log_info_repository'] as $variable) {
                    $target = new \Tests\Generator\MySql\Statement\Variable\SystemTargetGenerator(\Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::constant($variable), Generators::constant(null)), Generators::constant(\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Global));
                    $assignments[] = new \Tests\Generator\MySql\Statement\Utility\Set\SystemAssignmentGenerator($target, new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(strings: Generators::constant('TABLE')));
                }
                $prerequisites[] = new \Tests\Generator\MySql\Statement\Utility\Set\SetVariablesGenerator($release, \Tests\Generator\MySql\Statement\Utility\Set\SetVariablesGenerator::operands(Generators::tuple(...$assignments)));
            }
            return Generators::tuple(...array_merge($prerequisites, [
                new \Tests\Generator\MySql\Statement\Replication\Source\ChangeReplicationSourceGenerator($release, \Tests\Generator\MySql\Statement\Replication\Source\ChangeReplicationSourceGenerator::operands($words, Generators::tuple($host, $option), $channel)),
                new StartReplicaGenerator($release, StartReplicaGenerator::operands($words, Generators::tuple(ReplicaThreadGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaThread::Applier])), channel: $channel)),
                new self($release, self::operands($words, Generators::seq(new ReplicaThreadGenerator()), $channel)),
                new self($release, self::operands($words, channel: $channel)),
            ]));
        });
    }
}
