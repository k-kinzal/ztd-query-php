<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Reset;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ResetPersist values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist>
 */
final class ResetPersistGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist> */
    use Composition;

    /**
     * @param ErisGenerator<bool>|null $ifExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $variable Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $component Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $ifExists = null, ?ErisGenerator $variable = null, ?ErisGenerator $component = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist(ifExists: $parts[0], variable: $parts[1], component: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $ifExists ?? Generators::constant(false),
            $variable ?? Generators::constant(null),
            $component ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist>>
     */
    public static function forms(): iterable
    {
        yield 'ResetPersist/66' => new ResetPersistGenerator(
            ifExists: Generators::constant(false),
            variable: Generators::constant(null),
            component: Generators::constant(null)
        );
    }

}
