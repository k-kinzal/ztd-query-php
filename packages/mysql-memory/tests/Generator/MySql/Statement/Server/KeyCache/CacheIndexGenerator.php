<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\KeyCache;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
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
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable>> $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null> $cache Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $tables, ErisGenerator $cache, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex {
            return new \SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex(tables: $parts[0], cache: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $tables,
            $cache,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex>>
     */
    public static function forms(): iterable
    {
        yield 'CacheIndex/58' => new CacheIndexGenerator(
            tables: \Tests\Generator\Operands::tuple(
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
        yield 'CacheIndex/238' => new CacheIndexGenerator(
            tables: \Tests\Generator\Operands::tuple(
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
        yield 'CacheIndex/349' => new CacheIndexGenerator(
            tables: \Tests\Generator\Operands::tuple(
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
                    indexes: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Relation\Hint\PrimaryIndexGenerator()
                    )
                )
            ),
            cache: Generators::constant(null)
        );
    }

}
