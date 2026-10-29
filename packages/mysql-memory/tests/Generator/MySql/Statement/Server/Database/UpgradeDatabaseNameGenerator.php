<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UpgradeDatabaseName values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName>
 */
final class UpgradeDatabaseNameGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName|\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseWord>|null $databaseWord Generated database keyword
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $databaseWord = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName(name: $parts[0], statementInformation: $parts[1], inputSource: $parts[2], databaseWord: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $databaseWord ?? new DatabaseWordGenerator()
        ));
    }
    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName>}>
     */
    public static function additionalForms(): iterable
    {
        foreach ([\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744] as $release) {
            yield 'UpgradeDatabaseName/retained-name/' . $release->value => [$release, new self(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::elements(['ordinary', '#mysql50#', '#mysql50#a/b', '#mysql50#a.b', '#mysql50#' . str_repeat('a', 65), '🦄']),
                    quote: Generators::constant(\SqlSemantics\Statement\Identifier\NameQuote::Backtick)
                )
            )];
        }
        yield 'UpgradeDatabaseName/additional/57' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, new UpgradeDatabaseNameGenerator(
            name: new LegacyDatabaseNameGenerator(
                suffix: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('🦄'),
                    quote: Generators::constant(null)
                )
            )
        )];
    }

}
