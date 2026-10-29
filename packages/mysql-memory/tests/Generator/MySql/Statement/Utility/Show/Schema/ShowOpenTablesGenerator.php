<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Schema;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes ShowOpenTables values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables>
 */
final class ShowOpenTablesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables> */
    use Composition;

    /**
     * Generates complete ShowOpenTables values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No ShowOpenTables forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables) {
                throw new LogicException('Publishing ShowOpenTables must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<?\SqlSemantics\Statement\Identifier\Name>|null $database Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike|\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere|null>|null $filter Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $databaseIn Generated qualifier spelling
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables>
     */
    public static function operands(?ErisGenerator $database = null, ?ErisGenerator $filter = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $databaseIn = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables(database: $parts[0], filter: $parts[1], relationInformation: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], databaseIn: $parts[0] !== null && $parts[5]);
        }, Generators::tuple($database ?? Generators::constant(null), $filter ?? Generators::constant(null), $relationInformation ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $databaseIn ?? Generators::bool()));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables>>
     */
    public static function forms(): iterable
    {
        yield 'ShowOpenTables/102' => ShowOpenTablesGenerator::operands(
            database: Generators::oneOf(Generators::constant(null), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()),
            filter: Generators::constant(null)
        );
    }

}
