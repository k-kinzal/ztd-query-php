<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Instance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
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
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\InstanceAction> $action Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $action, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance(action: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $action,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance>>
     */
    public static function forms(): iterable
    {
        yield 'AlterInstance/5' => new AlterInstanceGenerator(
            action: new InstanceActionGenerator([new ReloadTlsGenerator(
                channel: Generators::constant(null),
                noRollbackOnError: Generators::constant(false)
            )])
        );
        yield 'AlterInstance/391' => new AlterInstanceGenerator(
            action: new InstanceActionGenerator([new RotateMasterKeyGenerator(
                keyring: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('INNODB'),
                    quote: Generators::constant(null)
                )
            )])
        );
        yield 'AlterInstance/392' => new AlterInstanceGenerator(
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
        yield 'AlterInstance/393' => new AlterInstanceGenerator(
            action: new InstanceActionGenerator([new ReloadKeyringGenerator()])
        );
    }

}
