<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type\Resolved;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Settings values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings>
 */
final class SettingsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation> $connection Generated constructor operand
     * @param ErisGenerator<int>|null $divPrecisionIncrement Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation|null>|null $server Generated constructor operand
     * @param ErisGenerator<array<array-key, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation>>|null $schemas Generated constructor operand
     * @param ErisGenerator<int>|null $groupConcatMaxLen Generated constructor operand
     * @param ErisGenerator<array<array-key, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain|\SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry>|null>|null $userVariables Generated constructor operand
     * @param ErisGenerator<array<int, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain>>|null $parameters Generated constructor operand
     * @param ErisGenerator<bool>|null $unsignedSubtraction Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null>|null $client Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramRow>>|null $program Generated constructor operand
     * @param ErisGenerator<array<array-key, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain>>|null $functions Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale|null>|null $timeNames Generated constructor operand
     * @param ErisGenerator<string>|null $blockEncryptionMode Generated constructor operand
     * @param ErisGenerator<bool>|null $derivedMerge Generated constructor operand
     * @param ErisGenerator<bool>|null $strict Generated constructor operand
     * @param ErisGenerator<bool|null>|null $explicitDefaultsForTimestamp Generated constructor operand
     * @param ErisGenerator<bool>|null $noZeroDate Generated constructor operand
     * @param ErisGenerator<bool>|null $noZeroInDate Generated constructor operand
     * @param ErisGenerator<bool>|null $allowInvalidDates Generated constructor operand
     * @param ErisGenerator<array<array-key, \SqlSemantics\Statement\Identifier\QualifiedName>>|null $handlers Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation|null>|null $databaseCollation Generated constructor operand
     * @param ErisGenerator<int|null>|null $replicaNetTimeout Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>|null>|null $fullTextParsers Generated constructor operand
     * @param ErisGenerator<bool>|null $realAsFloat Generated constructor operand
     * @param ErisGenerator<array<array-key, int>>|null $unboundParameters Generated constructor operand
     */
    public function __construct(ErisGenerator $connection, ?ErisGenerator $divPrecisionIncrement = null, ?ErisGenerator $server = null, ?ErisGenerator $schemas = null, ?ErisGenerator $groupConcatMaxLen = null, ?ErisGenerator $userVariables = null, ?ErisGenerator $parameters = null, ?ErisGenerator $unsignedSubtraction = null, ?ErisGenerator $client = null, ?ErisGenerator $program = null, ?ErisGenerator $functions = null, ?ErisGenerator $timeNames = null, ?ErisGenerator $blockEncryptionMode = null, ?ErisGenerator $derivedMerge = null, ?ErisGenerator $strict = null, ?ErisGenerator $explicitDefaultsForTimestamp = null, ?ErisGenerator $noZeroDate = null, ?ErisGenerator $noZeroInDate = null, ?ErisGenerator $allowInvalidDates = null, ?ErisGenerator $handlers = null, ?ErisGenerator $databaseCollation = null, ?ErisGenerator $replicaNetTimeout = null, ?ErisGenerator $fullTextParsers = null, ?ErisGenerator $unboundParameters = null, ?ErisGenerator $realAsFloat = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(connection: $parts[0], divPrecisionIncrement: $parts[1], server: $parts[2], schemas: $parts[3], groupConcatMaxLen: $parts[4], userVariables: $parts[5], parameters: $parts[6], unsignedSubtraction: $parts[7], client: $parts[8], program: $parts[9], functions: $parts[10], timeNames: $parts[11], blockEncryptionMode: $parts[12], derivedMerge: $parts[13], strict: $parts[14], explicitDefaultsForTimestamp: $parts[15], noZeroDate: $parts[16], noZeroInDate: $parts[17], allowInvalidDates: $parts[18], handlers: $parts[19], databaseCollation: $parts[20], replicaNetTimeout: $parts[21], fullTextParsers: $parts[22], unboundParameters: $parts[23], realAsFloat: $parts[24]);
        }, Generators::tuple(
            $connection,
            $divPrecisionIncrement ?? Generators::constant(4),
            $server ?? Generators::constant(null),
            $schemas ?? Generators::constant([]),
            $groupConcatMaxLen ?? Generators::constant(1024),
            $userVariables ?? Generators::constant(null),
            $parameters ?? Generators::constant([]),
            $unsignedSubtraction ?? Generators::constant(true),
            $client ?? Generators::constant(null),
            $program ?? Generators::constant([]),
            $functions ?? Generators::constant([]),
            $timeNames ?? Generators::constant(null),
            $blockEncryptionMode ?? Generators::constant('aes-128-ecb'),
            $derivedMerge ?? Generators::constant(true),
            $strict ?? Generators::constant(true),
            $explicitDefaultsForTimestamp ?? Generators::constant(null),
            $noZeroDate ?? Generators::constant(true),
            $noZeroInDate ?? Generators::constant(true),
            $allowInvalidDates ?? Generators::constant(false),
            $handlers ?? Generators::constant([]),
            $databaseCollation ?? Generators::constant(null),
            $replicaNetTimeout ?? Generators::constant(null),
            $fullTextParsers ?? Generators::constant(null),
            $unboundParameters ?? Generators::constant([]),
            $realAsFloat ?? Generators::constant(false)
        ));
    }

    /**
     * Varies locale while retaining the connection defaults of the execution session.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings>
     */
    public static function localized(): ErisGenerator
    {
        return Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale $locale): \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings => new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(
            \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_unicode_ci'),
            client: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'),
            timeNames: $locale,
        ), LocaleGenerator::catalog());
    }
}
