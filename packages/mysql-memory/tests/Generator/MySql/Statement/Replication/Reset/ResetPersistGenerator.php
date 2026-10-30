<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Reset;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete ResetPersist values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No ResetPersist forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist) {
                throw new LogicException('Publishing ResetPersist must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<bool>|null $ifExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $variable Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $component Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist>
     */
    public static function operands(?ErisGenerator $ifExists = null, ?ErisGenerator $variable = null, ?ErisGenerator $component = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist(ifExists: $parts[0], variable: $parts[1], component: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, Generators::tuple($ifExists ?? Generators::constant(false), $variable ?? Generators::constant(null), $component ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist>>
     */
    public static function forms(): iterable
    {
        yield 'all persisted variables' => self::operands();
        yield 'named persisted variables' => self::operands(Generators::bool(), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::oneOf(Generators::elements('max_connections', 'autocommit', 'sql_mode'), Generators::map(static fn (int $number): string => 'variable_' . $number, Generators::choose(0, 999))), Generators::constant(null)), Generators::oneOf(Generators::constant(null), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::elements('default', 'component'), Generators::constant(null))));
    }

}
