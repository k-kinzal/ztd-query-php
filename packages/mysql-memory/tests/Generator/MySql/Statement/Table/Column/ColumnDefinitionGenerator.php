<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ColumnDefinition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition>
 */
final class ColumnDefinitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition> */
    use Composition;

    /**
     * Composes declared sizes on both sides of MySQL's type-specific bounds.
     * The statement owns their SQL conditions; every written size remains shrinkable.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition>
     */
    public static function sized(): ErisGenerator
    {
        $sizes = Generators::oneOf(Generators::map(static fn (int $size): string => (string) $size, Generators::choose(0, 300)), Generators::elements('0', '1', '30', '31', '53', '54', '64', '65', '255', '256', '4294967295', '4294967296'));
        $types = new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([
            new \Tests\Generator\MySql\Statement\Type\FloatingGenerator(new \Tests\Generator\MySql\Statement\Type\Kind\FloatingKindGenerator(), $sizes, $sizes),
            new \Tests\Generator\MySql\Statement\Type\DecimalGenerator($sizes, $sizes),
            new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(new \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator(), $sizes),
            new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit), $sizes),
            new \Tests\Generator\MySql\Statement\Type\CharacterGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::VarChar), $sizes),
            new \Tests\Generator\MySql\Statement\Type\TemporalGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind::Time), Generators::map(static fn (int $size): string => (string) $size, Generators::choose(0, 9))),
        ]);
        $names = \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::map(static fn (int $id): string => 'c_' . $id, Generators::choose(0, 8)), Generators::constant(null));
        return new self(\Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands($names, Generators::constant(null), Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted)), new OrdinaryColumnGenerator($types));
    }

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ColumnName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification> $specification Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $specification, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(name: $parts[0], specification: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $name,
            $specification,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
