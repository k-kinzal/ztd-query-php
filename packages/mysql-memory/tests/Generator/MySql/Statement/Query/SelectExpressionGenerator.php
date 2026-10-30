<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Platform\MySql\Rules\Query\ItemNaming;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ItemLayouts;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;
use Tests\Generator\Semantics\Contract\LanguageProfileGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates SelectExpression values from the generators of its operands.
 *
 * @implements ErisGenerator<SelectExpression>
 */
final class SelectExpressionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SelectExpression> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static function (array $parts): SelectExpression {
                [$expression, $alias, $mark, $profile, $gaps] = $parts;
                if ($alias !== null) {
                    return new SelectExpression(expression: $expression, alias: $alias, layout: null, mark: $mark, spellingProfile: null, inputSource: null);
                }
                if ($profile === null || $gaps === null || !(new ItemNaming($profile))->keeps($expression)) {
                    return new SelectExpression(expression: $expression, alias: null, layout: null, mark: AliasMark::As, spellingProfile: null, inputSource: null);
                }
                $naming = new ItemNaming($profile);
                $output = new Output(Platforms::of('mysql')->codec($profile));
                $expression->render($output);
                $lexical = new Lexical();
                $tokens = [];
                $previous = null;
                foreach ($output->canonical() as $index => $piece) {
                    $tight = $previous !== null && $lexical->join([$previous, $piece]) === $previous->text . $piece->text;
                    $tokens[] = new Spelled($previous === null || $tight ? '' : $gaps[$index % count($gaps)], $piece->text);
                    $previous = $piece;
                }
                $layout = new Layout($tokens, '');
                try {
                    $read = array_values(array_filter(Platforms::of('mysql')->parser($profile)->tokenize($layout->text()), static fn (Token $token): bool => $token->text !== ''));
                } catch (SourceException) {
                    return new SelectExpression(expression: $expression, alias: null, layout: null, mark: AliasMark::As, spellingProfile: null, inputSource: null);
                }
                if (count($read) !== count($tokens) || array_map(static fn (Token $token): string => $token->text, $read) !== array_map(static fn (Spelled $token): string => $token->text, $tokens)) {
                    return new SelectExpression(expression: $expression, alias: null, layout: null, mark: AliasMark::As, spellingProfile: null, inputSource: null);
                }
                if ($layout->text() === $naming->canonical($expression)) {
                    return new SelectExpression(expression: $expression, alias: null, layout: null, mark: AliasMark::As, spellingProfile: null, inputSource: null);
                }

                return (new ItemLayouts())->problem($expression, $layout, $profile) !== null ? new SelectExpression(expression: $expression, alias: null, layout: null, mark: AliasMark::As, spellingProfile: null, inputSource: null) : new SelectExpression(expression: $expression, alias: null, layout: $layout, mark: AliasMark::As, spellingProfile: $profile, inputSource: null);
            },
            Generators::tuple(
                new ScalarGenerator($release, $depth - 1),
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                Generators::elements([AliasMark::As, AliasMark::Bare]),
                Generators::oneOf(Generators::constant(null), new LanguageProfileGenerator($release)),
                Generators::oneOf(Generators::constant(null), Generators::vector(3, Generators::elements([' ', '  '])))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (SelectExpression $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<SelectExpression>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<SelectExpression> $element The generated counterexample
     * @return GeneratedValue<SelectExpression>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
