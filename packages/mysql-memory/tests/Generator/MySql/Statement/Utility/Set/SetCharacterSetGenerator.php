<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SetCharacterSet values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet>
 */
final class SetCharacterSetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CharsetName> $charset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm>|null $form Generated constructor operand
     */
    public function __construct(ErisGenerator $charset, ?ErisGenerator $inputSource = null, ?ErisGenerator $form = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet(charset: $parts[0], inputSource: $parts[1], form: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $charset,
            $inputSource ?? Generators::constant(null),
            $form ?? \Tests\Generator\MySql\Statement\Type\Kind\CharsetFormGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Named, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::CharacterSet, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::CharSet])
        ));
    }

    /**
     * Composes valid connection assignments with the retained keyword and name delimiters.
     *
     * @return ErisGenerator<\SqlSemantics\Statement\AnalyzedStatement>
     */
    public static function assignments(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $names = new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(
            Generators::elements(['binary', 'latin1', 'utf8mb4']),
            new \Tests\Generator\Semantics\Statement\Identifier\NameQuoteGenerator(\SqlSemantics\Statement\Identifier\NameQuote::Bare, \SqlSemantics\Statement\Identifier\NameQuote::Backtick),
        );
        $items = Generators::vector(1, new self(new \Tests\Generator\MySql\Statement\Name\CharsetNameGenerator($names)));
        return new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, new SetVariablesGenerator($items));
    }
}
