<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AlterTablespace values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespace>
 */
final class AlterTablespaceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespace> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption>> $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<list<bool>>|null $optionsCommas Generated list boundaries
     */
    public function __construct(ErisGenerator $name, ErisGenerator $options, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $optionsCommas = null)
    {
        $this->delegate = \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple(
            $name,
            $options,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ), static function (array $parts) use ($optionsCommas): ErisGenerator {
            return Generators::map(
                static fn (array $commas): \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespace => new \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespace(name: $parts[0], options: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], optionsCommas: $commas),
                $optionsCommas ?? \Tests\Generator\Operands::vector(max(0, count($parts[1]) - 1), Generators::bool())
            );
        });
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespace>>
     */
    public static function forms(): iterable
    {
        yield 'AlterTablespace/179' => new AlterTablespaceGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\StorageOptionGenerator([new Option\WaitOptionGenerator(
                    wait: Generators::constant(true)
                )]),
                new Option\StorageOptionGenerator([new Option\WaitOptionGenerator(
                    wait: Generators::constant(true)
                )])
            )
        );
    }

}
