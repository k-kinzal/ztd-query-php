<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Instance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes AlterInstance values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance>
 */
final class AlterInstanceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance> */
    use Composition;

    /**
     * Generates complete AlterInstance values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No AlterInstance forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance) {
                throw new LogicException('Publishing AlterInstance must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\InstanceAction> $action Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance>
     */
    public static function operands(ErisGenerator $action, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance(action: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple($action, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance>>
     */
    public static function forms(): iterable
    {
        yield 'AlterInstance/5' => AlterInstanceGenerator::operands(
            action: new InstanceActionGenerator([new ReloadTlsGenerator(
                channel: Generators::constant(null),
                noRollbackOnError: Generators::constant(false)
            )])
        );
        yield 'AlterInstance/391' => AlterInstanceGenerator::operands(
            action: new InstanceActionGenerator([new RotateMasterKeyGenerator(
                keyring: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('INNODB'),
                    quote: Generators::constant(null)
                )
            )])
        );
        yield 'AlterInstance/392' => AlterInstanceGenerator::operands(
            action: new InstanceActionGenerator([new RedoLogSwitchGenerator(
                enable: Generators::constant(true),
                engine: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('INNODB'),
                    quote: Generators::constant(null)
                ),
                log: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('REDO_LOG'),
                    quote: Generators::constant(null)
                )
            )])
        );
        yield 'AlterInstance/393' => AlterInstanceGenerator::operands(
            action: new InstanceActionGenerator([new ReloadKeyringGenerator()])
        );
    }

}
