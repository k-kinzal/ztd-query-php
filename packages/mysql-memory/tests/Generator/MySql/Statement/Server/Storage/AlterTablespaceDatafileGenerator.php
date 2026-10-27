<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AlterTablespaceDatafile values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile>
 */
final class AlterTablespaceDatafileGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\DatafileAction> $action Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $datafile Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $action, ErisGenerator $datafile, ?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile(name: $parts[0], action: $parts[1], datafile: $parts[2], options: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $action,
            $datafile,
            $options ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile>>
     */
    public static function forms(): iterable
    {
        yield 'AlterTablespaceDatafile/178' => new AlterTablespaceDatafileGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            action: Option\DatafileActionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\DatafileAction::Add]),
            datafile: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                value: Generators::constant('text'),
                radix: Generators::constant(null)
            ),
            options: Generators::constant([])
        );
    }

}
