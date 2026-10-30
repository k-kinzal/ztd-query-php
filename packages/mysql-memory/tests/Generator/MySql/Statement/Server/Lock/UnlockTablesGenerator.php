<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Lock;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes UnlockTables values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables>
 */
final class UnlockTablesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables> */
    use Composition;

    /**
     * Generates complete UnlockTables values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if (str_starts_with($release->value, 'mysql-')) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No UnlockTables forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables) {
                throw new LogicException('Publishing UnlockTables must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\TableWord>|null $tableWord The generated table-list keyword
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables>
     */
    public static function operands(?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $tableWord = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables(statementInformation: $parts[0], inputSource: $parts[1], tableWord: $parts[2]);
        }, Generators::tuple($statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $tableWord ?? new \Tests\Generator\MySql\Statement\Table\TableWordGenerator()));
    }
    /**
     * Composes the supported releases' table-unlocking forms from typed operands.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables>>
     */
    public static function forms(): iterable
    {
        yield 'UnlockTables/390' => UnlockTablesGenerator::operands();
    }

}
