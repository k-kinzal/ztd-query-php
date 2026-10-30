<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Filter;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaThread;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use Tests\Generator\Composition;

/**
 * Composes ChangeReplicationFilter values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter>
 */
final class ChangeReplicationFilterGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter> */
    use Composition;

    /**
     * Generates complete ChangeReplicationFilter values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<ChangeReplicationFilter>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($release->database() !== 'mysql' || $release === GrammarRelease::MySql5651) {
            throw new InvalidArgumentException('CHANGE REPLICATION FILTER needs MySQL 5.7 or later.');
        }
        $operands ??= self::operands(self::filters(), self::channels($release));
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): ChangeReplicationFilter {
            if (!$statement instanceof ChangeReplicationFilter) {
                throw new LogicException('Publishing ChangeReplicationFilter must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter>> $filters Generated constructor operand
     * @param ErisGenerator<Text|null>|null $channel Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<ChangeReplicationFilter>
     */
    public static function operands(ErisGenerator $filters, ?ErisGenerator $channel = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): ChangeReplicationFilter {
            return new ChangeReplicationFilter(filters: $parts[0], channel: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple($filters, $channel ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Generates every filter kind, including empty and repeated replacement lists.
     *
     * @return ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter>>
     */
    public static function filters(): ErisGenerator
    {
        $name = new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator();
        $values = Generators::oneOf(
            new ReplicationFilterGenerator(FilterKindGenerator::selecting([FilterKind::DoDb, FilterKind::IgnoreDb]), Generators::seq($name)),
            new ReplicationFilterGenerator(FilterKindGenerator::selecting([FilterKind::DoTable, FilterKind::IgnoreTable]), Generators::seq(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator($name, $name))),
            new ReplicationFilterGenerator(FilterKindGenerator::selecting([FilterKind::WildDoTable, FilterKind::WildIgnoreTable]), Generators::seq(new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (array $parts): string => $parts[0]->value . '.' . $parts[1], Generators::tuple($name, Generators::elements(['%', '_', 'table%'])))))),
            new ReplicationFilterGenerator(FilterKindGenerator::selecting([FilterKind::RewriteDb]), Generators::seq(new DatabaseRewriteGenerator($name, $name))),
        );
        return Generators::bind(Generators::choose(1, 4), static fn (int $count): ErisGenerator => Generators::vector($count, $values));
    }

    /**
     * Generates unqualified requests, the explicit default channel, and named channels.
     *
     * @return ErisGenerator<Text|null>
     */
    public static function channels(GrammarRelease $release): ErisGenerator
    {
        return $release === GrammarRelease::MySql5744 ? Generators::constant(null) : Generators::oneOf(
            Generators::constant(null),
            new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::constant('')),
            new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $value): string => 'channel_' . $value, Generators::choose(0, 20))),
        );
    }

    /**
     * Composes source configuration, applier start/stop, and the dependent filter change.
     * Channel and filter shrinking rebuilds every dependent statement together.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement}>
     */
    public static function sequences(GrammarRelease $release): ErisGenerator
    {
        return Generators::map(static function (array $parts) use ($release): array {
            [$channel, $filters] = $parts;
            $terminology = (new \SqlSemantics\Platform\MySql\Rules\Replication\Releases())->source($release);
            $source = new ChangeReplicationSource($terminology, [new SourceOption($terminology, SourceOptionKind::Host, new Text('127.0.0.1'))], $channel);
            $start = new StartReplica($terminology, [ReplicaThread::Applier], channel: $channel);
            $stop = new StopReplica($terminology, [ReplicaThread::Applier], channel: $channel);
            $filter = new ChangeReplicationFilter($filters, $channel);
            $context = new AnalysisContext(new LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('pbt')]);
            $publication = new Publication();
            return [$publication->publish($context, $source), $publication->publish($context, $start), $publication->publish($context, $stop), $publication->publish($context, $filter)];
        }, Generators::tuple(self::channels($release), self::filters()));
    }
}
