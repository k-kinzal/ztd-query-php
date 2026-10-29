<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Filter;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter>|null $operands A constrained form of this concrete result
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

            if ($forms === []) {
                throw new InvalidArgumentException('No ChangeReplicationFilter forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter) {
                throw new LogicException('Publishing ChangeReplicationFilter must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter>> $filters Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $channel Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter>
     */
    public static function operands(ErisGenerator $filters, ?ErisGenerator $channel = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter(filters: $parts[0], channel: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple($filters, $channel ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter>>
     */
    public static function forms(): iterable
    {
        yield 'ChangeReplicationFilter/131' => ChangeReplicationFilterGenerator::operands(
            filters: Generators::tuple(
                new ReplicationFilterGenerator(
                    kind: FilterKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind::DoDb]),
                    values: Generators::constant([])
                )
            ),
            channel: Generators::constant(null)
        );
        yield 'ChangeReplicationFilter/132' => ChangeReplicationFilterGenerator::operands(
            filters: Generators::tuple(
                new ReplicationFilterGenerator(
                    kind: FilterKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind::RewriteDb]),
                    values: Generators::tuple(
                        new DatabaseRewriteGenerator(
                            from: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            to: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            )
                        )
                    )
                )
            ),
            channel: Generators::constant(null)
        );
    }

}
