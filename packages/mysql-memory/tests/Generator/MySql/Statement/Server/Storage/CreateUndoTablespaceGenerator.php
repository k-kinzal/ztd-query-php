<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CreateUndoTablespace values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateUndoTablespace>
 */
final class CreateUndoTablespaceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateUndoTablespace> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $datafile Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<list<bool>>|null $optionsCommas Generated list boundaries
     */
    public function __construct(ErisGenerator $name, ErisGenerator $datafile, ?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $optionsCommas = null)
    {
        $this->delegate = \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple(
            $name,
            $datafile,
            $options ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ), static function (array $parts) use ($optionsCommas): ErisGenerator {
            return Generators::map(
                static fn (array $commas): \SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateUndoTablespace => new \SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateUndoTablespace(name: $parts[0], datafile: $parts[1], options: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], optionsCommas: $commas),
                $optionsCommas ?? \Tests\Generator\Operands::vector(max(0, count($parts[2]) - 1), Generators::bool())
            );
        });
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateUndoTablespace>>
     */
    public static function forms(): iterable
    {
        yield 'CreateUndoTablespace/142' => new CreateUndoTablespaceGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                value: Generators::constant('text'),
                radix: Generators::constant(null)
            ),
            options: Generators::constant([])
        );
    }

}
