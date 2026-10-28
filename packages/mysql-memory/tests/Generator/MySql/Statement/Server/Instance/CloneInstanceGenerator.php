<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Instance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CloneInstance values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneInstance>
 */
final class CloneInstanceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneInstance> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account> $donor Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $port Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $password Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $directory Generated constructor operand
     * @param ErisGenerator<bool|null>|null $requireSsl Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $equals Generated constructor operand
     * @param ErisGenerator<bool>|null $assignment The generated written alternative
     */
    public function __construct(ErisGenerator $donor, ErisGenerator $port, ErisGenerator $password, ?ErisGenerator $directory = null, ?ErisGenerator $requireSsl = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $equals = null, ?ErisGenerator $assignment = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneInstance {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneInstance(donor: $parts[0], port: $parts[1], password: $parts[2], directory: $parts[3], requireSsl: $parts[4], statementInformation: $parts[5], inputSource: $parts[6], equals: $parts[7], assignment: $parts[8] && $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $donor,
            $port,
            $password,
            $directory ?? Generators::constant(null),
            $requireSsl ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $equals ?? Generators::bool(),
            $assignment ?? Generators::bool()
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneInstance>>
     */
    public static function forms(): iterable
    {
        yield 'CloneInstance/354' => new CloneInstanceGenerator(
            donor: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            port: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                text: Generators::constant('0f'),
                hexadecimal: Generators::constant(true)
            ),
            password: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                value: Generators::constant('text'),
                radix: Generators::constant(null)
            ),
            directory: Generators::constant(null),
            requireSsl: Generators::constant(null)
        );
    }

}
