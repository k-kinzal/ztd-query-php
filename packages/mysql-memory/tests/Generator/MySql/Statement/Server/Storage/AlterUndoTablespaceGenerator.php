<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AlterUndoTablespace values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace>
 */
final class AlterUndoTablespaceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<bool> $active Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $active, ?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace(name: $parts[0], active: $parts[1], options: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $active,
            $options ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace>>
     */
    public static function forms(): iterable
    {
        yield 'AlterUndoTablespace/10' => new AlterUndoTablespaceGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            active: Generators::constant(true),
            options: Generators::constant([])
        );
    }

}
