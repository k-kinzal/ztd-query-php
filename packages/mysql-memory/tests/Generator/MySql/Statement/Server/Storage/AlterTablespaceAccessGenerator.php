<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete AlterTablespaceAccess values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            foreach (self::additionalForms() as [$grammar, $form]) {
                if ($grammar === $release) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No AlterTablespaceAccess forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess) {
                throw new LogicException('Publishing AlterTablespaceAccess must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\TablespaceAccess> $access Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess>
     */
    public static function operands(ErisGenerator $name, ErisGenerator $access, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess(name: $parts[0], access: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple($name, $access, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'AlterTablespaceAccess/additional/59' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, AlterTablespaceAccessGenerator::operands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('ts'),
                quote: Generators::constant(null)
            ),
            access: Option\TablespaceAccessGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\TablespaceAccess::ReadWrite])
        )];
    }

}
