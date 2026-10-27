<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidUserAttribute values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidUserAttribute>
 */
final class InvalidUserAttributeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidUserAttribute> */
    use Composition;

    /**
     * @param ErisGenerator<string> $text Generated constructor operand
     */
    public function __construct(ErisGenerator $text)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidUserAttribute {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidUserAttribute(text: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $text
        ));
    }

    /**
     * Generates the complete dependency sequence that owns this command condition.
     *
     * @return ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Statement>>
     */
    public static function statements(): ErisGenerator
    {
        return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidUserAttribute $problem): array {
            $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '8.4.7', searchPath: new \SqlSemantics\Contract\SearchPath('pbt'));
            $comment = new \SqlSemantics\Platform\MySql\Statement\Account\Option\UserComment(\SqlSemantics\Platform\MySql\Statement\Account\Option\CommentKind::Attribute, new \SqlSemantics\Platform\MySql\Statement\Literal\Text($problem->text));
            $user = new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('generated_user')));
            return [(new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context(), new \SqlSemantics\Platform\MySql\Statement\Account\CreateUser(false, [$user], comment: $comment))];
        }, new self(Generators::map(static fn (int $number): string => '[' . $number . ']', Generators::choose(0, 32))));
    }

}
