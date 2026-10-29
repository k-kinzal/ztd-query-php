<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NameConversion values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\NameConversion>
 */
final class NameConversionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\NameConversion> */
    use Composition;

    /**
     * @param ErisGenerator<string> $text Generated constructor operand
     * @param ErisGenerator<string> $charset Generated constructor operand
     */
    public function __construct(ErisGenerator $charset, ErisGenerator $text)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\NameConversion {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\NameConversion(charset: $parts[0], text: $parts[1]);
        }, Generators::tuple(
            $charset,
            $text
        ));
    }
    /**
     * Generates an introduced literal whose unaliased field name needs conversion.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\QueryRoot>
     */
    public static function queries(): ErisGenerator
    {
        return Generators::bind(new self(Generators::constant('latin2'), Generators::map(static fn (int $suffix): string => "\xE9" . $suffix, Generators::choose(0, 9))), static function (\SqlSemantics\Platform\MySql\Statement\Query\Problem\NameConversion $missing): ErisGenerator {
            $collation = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known($missing->charset . '_general_ci');
            $domain = new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString, 2, collation: $collation, coercibility: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible);
            $literals = \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator::fromOperands(
                Generators::constant([$missing->text]),
                new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::constant($missing->charset), Generators::constant(null)),
                Generators::constant(false),
                Generators::constant($collation),
                scalarInformation: new \Tests\Generator\Semantics\Statement\Fact\ScalarFactGenerator(new \Tests\Generator\Semantics\Statement\Type\KnownGenerator(Generators::constant($domain)), Generators::constant(\SqlSemantics\Statement\Type\Nullability::NotNull)),
            );
            return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral $literal) use ($missing): \SqlSemantics\Platform\MySql\Statement\QueryRoot {
                $fact = $literal->scalarInformation();
                $field = new \SqlSemantics\Statement\Shape\Field(0, new \SqlSemantics\Statement\Shape\OutputSlot(null, $fact->type, $fact->nullability, unnamed: [$missing]), $literal);
                $output = new \SqlSemantics\Statement\Fact\QueryFact([$field], \SqlSemantics\Statement\Identifier\Comparison::AsciiInsensitive);
                return new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($literal)], queryInformation: $output), new \SqlSemantics\Platform\MySql\Statement\StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), output: $output, connection: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_unicode_ci')));
            }, $literals);
        });
    }

}
