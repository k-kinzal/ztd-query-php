<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\KeyCache;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes CacheIndex values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex>
 */
final class CacheIndexGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex> */
    use Composition;

    /**
     * Generates complete CacheIndex values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No CacheIndex forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex) {
                throw new LogicException('Publishing CacheIndex must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable>> $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null> $cache Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex>
     */
    public static function operands(ErisGenerator $tables, ErisGenerator $cache, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex {
            return new \SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex(tables: $parts[0], cache: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple($tables, $cache, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex>>
     */
    public static function forms(): iterable
    {
        yield 'CacheIndex/58' => CacheIndexGenerator::operands(
            tables: Generators::tuple(
                new CachedTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    partitions: Generators::constant(null),
                    indexes: Generators::constant(null)
                )
            ),
            cache: Generators::constant(null)
        );
        yield 'CacheIndex/238' => CacheIndexGenerator::operands(
            tables: Generators::tuple(
                new CachedTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    partitions: new \Tests\Generator\MySql\Statement\Partition\AllPartitionsGenerator(),
                    indexes: Generators::constant([])
                )
            ),
            cache: Generators::constant(null)
        );
        yield 'CacheIndex/349' => CacheIndexGenerator::operands(
            tables: Generators::tuple(
                new CachedTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    partitions: new \Tests\Generator\MySql\Statement\Partition\AllPartitionsGenerator(),
                    indexes: Generators::tuple(
                        new \Tests\Generator\MySql\Statement\Relation\Hint\PrimaryIndexGenerator()
                    )
                )
            ),
            cache: Generators::constant(null)
        );
    }

}
