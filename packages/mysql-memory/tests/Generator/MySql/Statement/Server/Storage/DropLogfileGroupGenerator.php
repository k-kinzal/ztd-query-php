<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes DropLogfileGroup values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup>
 */
final class DropLogfileGroupGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup> */
    use Composition;

    /**
     * Generates complete DropLogfileGroup values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When the release belongs to another database.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($release->database() !== 'mysql') {
            throw new InvalidArgumentException('Storage statements require a MySQL grammar release.');
        }
        if ($operands === null) {
            $forms = [self::operands(
                new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(),
                Generators::bind(Generators::choose(0, 4), static fn (int $count): ErisGenerator => Generators::vector($count, new Option\WaitOptionGenerator(Generators::bool()))),
                leadingComma: in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true) ? Generators::bool() : Generators::constant(false)
            )];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup) {
                throw new LogicException('Publishing DropLogfileGroup must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<list<bool>>|null $optionsCommas Generated list boundaries
     * @param ErisGenerator<bool>|null $leadingComma The optional comma before the first option
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup>
     */
    public static function operands(ErisGenerator $name, ?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $optionsCommas = null, ?ErisGenerator $leadingComma = null): ErisGenerator
    {
        return Generators::bind(Generators::tuple($name, $options ?? Generators::constant([]), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)), static function (array $parts) use ($optionsCommas, $leadingComma): ErisGenerator {
            return Generators::map(static fn (array $boundaries): \SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup => new \SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup(name: $parts[0], options: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], optionsCommas: $boundaries[0], leadingComma: $parts[1] !== [] && $boundaries[1]), Generators::tuple($optionsCommas ?? Generators::vector(max(0, count($parts[1]) - 1), Generators::bool()), $leadingComma ?? Generators::constant(false)));
        });
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup>>
     */
    public static function forms(): iterable
    {
        yield 'DropLogfileGroup/34' => DropLogfileGroupGenerator::operands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            options: Generators::constant([])
        );
    }

}
