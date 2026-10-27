<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Filter;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
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
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter>> $filters Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $channel Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $filters, ?ErisGenerator $channel = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter(filters: $parts[0], channel: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $filters,
            $channel ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter>>
     */
    public static function forms(): iterable
    {
        yield 'ChangeReplicationFilter/131' => new ChangeReplicationFilterGenerator(
            filters: \Tests\Generator\Operands::tuple(
                new ReplicationFilterGenerator(
                    kind: FilterKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind::DoDb]),
                    values: Generators::constant([])
                )
            ),
            channel: Generators::constant(null)
        );
        yield 'ChangeReplicationFilter/132' => new ChangeReplicationFilterGenerator(
            filters: \Tests\Generator\Operands::tuple(
                new ReplicationFilterGenerator(
                    kind: FilterKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind::RewriteDb]),
                    values: \Tests\Generator\Operands::tuple(
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
