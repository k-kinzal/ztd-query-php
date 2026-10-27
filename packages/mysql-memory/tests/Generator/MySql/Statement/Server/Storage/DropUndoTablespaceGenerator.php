<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DropUndoTablespace values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\DropUndoTablespace>
 */
final class DropUndoTablespaceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\DropUndoTablespace> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Storage\DropUndoTablespace {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Storage\DropUndoTablespace(name: $parts[0], options: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $options ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\DropUndoTablespace>>
     */
    public static function forms(): iterable
    {
        yield 'DropUndoTablespace/40' => new DropUndoTablespaceGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            options: Generators::constant([])
        );
    }

}
