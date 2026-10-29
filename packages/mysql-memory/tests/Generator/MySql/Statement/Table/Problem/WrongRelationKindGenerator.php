<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes WrongRelationKind values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind>
 */
final class WrongRelationKindGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal> $refusal Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $refusal)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind(name: $parts[0], refusal: $parts[1]);
        }, Generators::tuple(
            $name,
            $refusal
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\WrongRelationKind' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new WrongRelationKindGenerator(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 't' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'shop' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                catalog: Generators::constant(null)
            ),
            refusal: KindRefusalGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal::NotView])
        )];
    }

}
