<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete AlterUndoTablespace values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No AlterUndoTablespace forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace) {
                throw new LogicException('Publishing AlterUndoTablespace must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<bool> $active Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<list<bool>>|null $optionsCommas Generated list boundaries
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace>
     */
    public static function operands(ErisGenerator $name, ErisGenerator $active, ?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $optionsCommas = null): ErisGenerator
    {
        return Generators::bind(Generators::tuple($name, $active, $options ?? Generators::constant([]), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)), static function (array $parts) use ($optionsCommas): ErisGenerator {
            return Generators::map(static fn (array $commas): \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace => new \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace(name: $parts[0], active: $parts[1], options: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], optionsCommas: $commas), $optionsCommas ?? Generators::vector(max(0, count($parts[2]) - 1), Generators::bool()));
        });
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace>>
     */
    public static function forms(): iterable
    {
        yield 'AlterUndoTablespace/10' => AlterUndoTablespaceGenerator::operands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            active: Generators::constant(true),
            options: Generators::constant([])
        );
    }

}
