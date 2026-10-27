<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\View;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DropView values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\DropView>
 */
final class DropViewGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\View\DropView> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\QualifiedName>> $views Generated constructor operand
     * @param ErisGenerator<bool>|null $ifExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior|null>|null $behavior Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $views, ?ErisGenerator $ifExists = null, ?ErisGenerator $behavior = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\View\DropView {
            return new \SqlSemantics\Platform\MySql\Statement\View\DropView(views: $parts[0], ifExists: $parts[1], behavior: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $views,
            $ifExists ?? Generators::constant(false),
            $behavior ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\DropView>>
     */
    public static function forms(): iterable
    {
        yield 'DropView/43' => new DropViewGenerator(
            views: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                )
            ),
            ifExists: Generators::constant(false),
            behavior: Generators::constant(null)
        );
    }

}
