<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Statement\Relation;
use Tests\Generator\MySql\Statement\Alter\AlterTableGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\OpenHandlerGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\RowAliasGenerator;
use Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator;
use Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator;
use Tests\Generator\MySql\Statement\Relation\DerivedTableGenerator;
use Tests\Generator\MySql\Statement\Relation\DualGenerator;
use Tests\Generator\MySql\Statement\Relation\EscapedRelationGenerator;
use Tests\Generator\MySql\Statement\Relation\JoinedTableGenerator;
use Tests\Generator\MySql\Statement\Relation\JsonTableGenerator;
use Tests\Generator\MySql\Statement\Relation\NestedRelationGenerator;
use Tests\Generator\MySql\Statement\Relation\OdbcJoinGenerator;
use Tests\Generator\MySql\Statement\Relation\TableListGenerator;
use Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator;
use Tests\Generator\MySql\Statement\Routine\ParameterListGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\VariableDeclarationGenerator;
use Tests\Generator\MySql\Statement\Routine\Trigger\TriggerTableGenerator;
use Tests\Generator\MySql\Statement\Table\CreateIndexGenerator;
use Tests\Generator\MySql\Statement\Table\CreateTableGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowFunctionStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowProcedureStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowColumnsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowDatabasesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowEventsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowKeysGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowOpenTablesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTablesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTableStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTriggersGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowCharacterSetGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowCollationGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowVariablesGenerator;

/**
 * Generates Relation values from the generators of its operands.
 *
 * @implements ErisGenerator<Relation>
 */
final class RelationGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Relation> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Relation from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 511, 508, 511, 511, 3, 511, 508, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
            0 => new AlterTableGenerator($release),
            1 => new OpenHandlerGenerator($release),
            2 => new RowAliasGenerator($release),
            3 => new WriteTargetGenerator($release),
            4 => new ExplicitTableGenerator($release),
            5 => new DerivedTableGenerator($release),
            6 => new DualGenerator($release),
            7 => new EscapedRelationGenerator($release),
            8 => new JoinedTableGenerator($release),
            9 => new JsonTableGenerator($release),
            10 => new NestedRelationGenerator($release),
            11 => new OdbcJoinGenerator($release),
            12 => new TableListGenerator($release),
            13 => new TableReferenceGenerator($release),
            14 => new ParameterListGenerator($release),
            15 => new VariableDeclarationGenerator($release),
            16 => new TriggerTableGenerator($release),
            17 => new CreateIndexGenerator($release),
            18 => new CreateTableGenerator($release),
            19 => new ShowFunctionStatusGenerator($release),
            20 => new ShowProcedureStatusGenerator($release),
            21 => new ShowColumnsGenerator($release),
            22 => new ShowDatabasesGenerator($release),
            23 => new ShowEventsGenerator($release),
            24 => new ShowKeysGenerator($release),
            25 => new ShowOpenTablesGenerator($release),
            26 => new ShowTableStatusGenerator($release),
            27 => new ShowTablesGenerator($release),
            28 => new ShowTriggersGenerator($release),
            29 => new ShowCharacterSetGenerator($release),
            30 => new ShowCollationGenerator($release),
            31 => new ShowStatusGenerator($release),
            32 => new ShowVariablesGenerator($release),
            default => throw new LogicException('Unreachable alternative.'),
        });
        $this->delegate = Generators::suchThat(
            static function (Relation $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Relation>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Relation>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
