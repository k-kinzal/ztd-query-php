<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\ForeignServer;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes CreateServer values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer>
 */
final class CreateServerGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer> */
    use Composition;

    /**
     * Generates complete CreateServer values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When the release belongs to another database.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($release->database() !== 'mysql') {
            throw new InvalidArgumentException('Foreign servers require a MySQL grammar release.');
        }
        if ($operands === null) {
            $forms = [self::requests()];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer) {
                throw new LogicException('Publishing CreateServer must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * Generates names, wrappers and repeated options, retaining empty-name conditions.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer>
     */
    public static function requests(): ErisGenerator
    {
        $names = \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
            Generators::oneOf(Generators::constant(''), Generators::map(static fn (int $length): string => str_repeat('s', $length), Generators::choose(1, 80))),
            Generators::oneOf(Generators::constant(null), new \Tests\Generator\Semantics\Statement\Identifier\NameQuoteGenerator(\SqlSemantics\Statement\Identifier\NameQuote::SingleQuote, \SqlSemantics\Statement\Identifier\NameQuote::Backtick))
        );
        $wrappers = \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::elements('', 'mysql', 'unknown', '😀'), Generators::oneOf(Generators::constant(null), new \Tests\Generator\Semantics\Statement\Identifier\NameQuoteGenerator(\SqlSemantics\Statement\Identifier\NameQuote::SingleQuote, \SqlSemantics\Statement\Identifier\NameQuote::Backtick)));
        $options = Generators::bind(new ServerOptionKindGenerator(), static fn (\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind $kind): ErisGenerator => new ServerOptionGenerator(
            Generators::constant($kind),
            $kind === \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind::Port
                ? new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::oneOf(Generators::map(static fn (int $port): string => (string) $port, Generators::choose(0, 70000)), Generators::elements('1.5', '18446744073709551616')))
                : new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::elements('', 'text', "a'b", '😀'))
        ));
        return Generators::bind(Generators::choose(1, 5), static fn (int $count): ErisGenerator => self::operands($names, $wrappers, Generators::vector($count, $options)));
    }

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $wrapper Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption>> $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer>
     */
    public static function operands(ErisGenerator $name, ErisGenerator $wrapper, ErisGenerator $options, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer {
            return new \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer(name: $parts[0], wrapper: $parts[1], options: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, Generators::tuple($name, $wrapper, $options, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer>>
     */
    public static function forms(): iterable
    {
        yield 'CreateServer/143' => CreateServerGenerator::operands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            wrapper: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            options: Generators::tuple(
                new ServerOptionGenerator(
                    kind: ServerOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind::User]),
                    value: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    )
                )
            )
        );
    }

}
