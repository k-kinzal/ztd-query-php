<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AlterTablespaceAccess values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess>
 */
final class AlterTablespaceAccessGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\TablespaceAccess> $access Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $access, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess(name: $parts[0], access: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $access,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'AlterTablespaceAccess/additional/59' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, new AlterTablespaceAccessGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('ts'),
                quote: Generators::constant(null)
            ),
            access: Option\TablespaceAccessGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\TablespaceAccess::ReadWrite])
        )];
    }

}
