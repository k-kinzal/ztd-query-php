<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Contract;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AnalysisContext values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Contract\AnalysisContext>
 */
final class AnalysisContextGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Contract\AnalysisContext> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Contract\LanguageProfile> $profile Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>> $searchPath Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Declaration\Table>>|null $tables Generated constructor operand
     * @param ErisGenerator<bool>|null $complete Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Comparison>|null $relationNames Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Comparison>|null $columnNames Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $declarationSchema Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Contract\Session|null>|null $session Generated constructor operand
     * @param ErisGenerator<list<string>>|null $foldedSchemas Generated constructor operand
     */
    public function __construct(ErisGenerator $profile, ErisGenerator $searchPath, ?ErisGenerator $tables = null, ?ErisGenerator $complete = null, ?ErisGenerator $relationNames = null, ?ErisGenerator $columnNames = null, ?ErisGenerator $declarationSchema = null, ?ErisGenerator $session = null, ?ErisGenerator $foldedSchemas = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Contract\AnalysisContext {
            return new \SqlSemantics\Contract\AnalysisContext(profile: $parts[0], searchPath: $parts[1], tables: $parts[2], complete: $parts[3], relationNames: $parts[4], columnNames: $parts[5], declarationSchema: $parts[6], session: $parts[7], foldedSchemas: $parts[8]);
        }, Generators::tuple(
            $profile,
            $searchPath,
            $tables ?? Generators::constant([]),
            $complete ?? Generators::constant(true),
            $relationNames ?? Generators::constant(\SqlSemantics\Statement\Identifier\Comparison::Sensitive),
            $columnNames ?? Generators::constant(\SqlSemantics\Statement\Identifier\Comparison::Sensitive),
            $declarationSchema ?? Generators::constant(null),
            $session ?? Generators::constant(null),
            $foldedSchemas ?? Generators::constant([])
        ));
    }

    /**
     * Generates the language and session inputs used to bind direct MySQL operands.
     * The profile, search path and settings remain explicit generator operands.
     *
     * @param ErisGenerator<\SqlSemantics\Contract\Mode>|null $modes The modes required by the generated SQL forms
     * @return ErisGenerator<\SqlSemantics\Contract\AnalysisContext>
     */
    public static function mysql(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $modes = null): ErisGenerator
    {
        $modes ??= new ModeGenerator([new \Tests\Generator\MySql\ModeGenerator()]);
        return Generators::map(static function (array $parts) use ($release): \SqlSemantics\Contract\AnalysisContext {
            [$mode, $settings, $dialect, $searchPath] = $parts;
            return (new \SqlSemantics\Facade\Semantics($dialect, substr($release->value, 6), $mode, searchPath: $searchPath, session: $settings))->context();
        }, Generators::tuple(
            $modes,
            new SessionGenerator([\Tests\Generator\MySql\Statement\Type\Resolved\SettingsGenerator::localized()]),
            new DialectGenerator([new \Tests\Generator\MySql\DialectGenerator()]),
            new SearchPathGenerator(Generators::constant('pbt')),
        ));
    }
}
