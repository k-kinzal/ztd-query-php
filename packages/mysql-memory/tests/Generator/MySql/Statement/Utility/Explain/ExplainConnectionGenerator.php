<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Explain;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ExplainConnection values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection>
 */
final class ExplainConnectionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $connection Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $format Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier|null>|null $modifier Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $analyze Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable|null>|null $into Generated constructor operand
     * @param ErisGenerator<bool>|null $emptyInto Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainVerb>|null $verb Generated command choice
     */
    public function __construct(ErisGenerator $connection, ?ErisGenerator $format = null, ?ErisGenerator $modifier = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $analyze = null, ?ErisGenerator $into = null, ?ErisGenerator $emptyInto = null, ?ErisGenerator $verb = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection(connection: $parts[0], format: $parts[1], modifier: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], analyze: $parts[5], into: $parts[6], emptyInto: $parts[7], verb: $parts[8]);
        }, \Tests\Generator\Operands::tuple(
            $connection,
            $format ?? Generators::constant(null),
            $modifier ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $analyze ?? Generators::constant(false),
            $into ?? Generators::constant(null),
            $emptyInto ?? Generators::constant(false),
            $verb ?? new ExplainVerbGenerator()
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection>>
     */
    public static function forms(): iterable
    {
        yield 'ExplainConnection/369' => new ExplainConnectionGenerator(
            connection: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                text: Generators::constant('1'),
                hexadecimal: Generators::constant(false)
            ),
            format: Generators::constant(null),
            modifier: Generators::constant(null)
        );
    }

    /**
     * Generates incompatible options with independent connection and variable names.
     * Each published request reaches execution directly, including during shrinking.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, int, int}>
     */
    public static function refusedOptions(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $options = [['XML', false, false, 1791, false]];
        if ($release === \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            $options[] = ['TREE', false, false, 1791, false];
        } else {
            $options[] = [null, true, false, 1235, false];
            $options[] = ['TRADITIONAL', true, false, 1235, false];
        }
        if (!in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5744, \SqlSemantics\Contract\GrammarRelease::MySql8044], true)) {
            $options[] = ['JSON', false, true, 6009, false];
            $options[] = [null, false, true, 6006, false];
            $options[] = ['TREE', false, true, 6007, false];
        }
        if (in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql820, \SqlSemantics\Contract\GrammarRelease::MySql830], true)) {
            $options[] = [null, false, false, 1094, true];
        }
        return Generators::bind(Generators::elements($options), static fn (array $option): ErisGenerator => self::refusal($release, $option));
    }

    /**
     * Composes a selected option case while retaining typed shrinking operands.
     *
     * @param array{string|null, bool, bool, int, bool} $option The report format, flags and expected error
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, int, int}>
     */
    public static function refusal(\SqlSemantics\Contract\GrammarRelease $release, array $option): ErisGenerator
    {
        [$format, $analyze, $into, $code, $empty] = $option;
        $request = new self(
            new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::map(static fn (int $id): string => (string) $id, Generators::choose(1000000, 1100000))),
            format: $format === null ? Generators::constant(null) : \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::elements($format, strtolower($format)), Generators::constant(null)),
            analyze: Generators::constant($analyze),
            into: $into ? new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(\Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::map(static fn (int $id): string => 'plan_' . $id, Generators::choose(0, 32)), Generators::constant(null))) : Generators::constant(null),
            emptyInto: Generators::constant($empty)
        );
        return Generators::map(static fn (\SqlSemantics\Statement\AnalyzedStatement $statement): array => [$statement, $code, $code === 1094 ? 1295 : $code], new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $request));
    }

}
