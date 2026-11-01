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
use SqlSemantics\Statement\TableUse;
use Tests\Generator\MySql\Statement\Account\Privilege\Level\ObjectLevelGenerator;
use Tests\Generator\MySql\Statement\Alter\AlterTableGenerator;
use Tests\Generator\MySql\Statement\Alter\DropIndexGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\ExchangePartitionGenerator;
use Tests\Generator\MySql\Statement\Alter\TableRenamingGenerator;
use Tests\Generator\MySql\Statement\Alter\TargetTableGenerator;
use Tests\Generator\MySql\Statement\Alter\TruncateTableGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\HandlerOpenGenerator;
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
use Tests\Generator\MySql\Statement\Server\KeyCache\CachedTableGenerator;
use Tests\Generator\MySql\Statement\Server\KeyCache\PreloadedTableGenerator;
use Tests\Generator\MySql\Statement\Server\Lock\TableLockGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\MaintainedTableGenerator;
use Tests\Generator\MySql\Statement\Table\CreateIndexGenerator;
use Tests\Generator\MySql\Statement\Table\CreateTableGenerator;
use Tests\Generator\MySql\Statement\Table\CreateTableLikeGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ReferencesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\InspectedTableGenerator;
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
use Tests\Generator\MySql\Statement\View\AlterViewGenerator;

/**
 * Generates TableUse values from the generators of its operands.
 *
 * @implements ErisGenerator<TableUse>
 */
final class TableUseGenerator implements ErisGenerator
{
    /** @var ErisGenerator<TableUse> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of TableUse from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 3, 511, 508, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
            0 => new ObjectLevelGenerator($release),
            1 => new AlterTableGenerator($release),
            2 => new DropIndexGenerator($release),
            3 => new ExchangePartitionGenerator($release),
            4 => new TableRenamingGenerator($release),
            5 => new TargetTableGenerator($release),
            6 => new TruncateTableGenerator($release),
            7 => new HandlerOpenGenerator($release),
            8 => new OpenHandlerGenerator($release),
            9 => new RowAliasGenerator($release),
            10 => new WriteTargetGenerator($release),
            11 => new ExplicitTableGenerator($release),
            12 => new DerivedTableGenerator($release),
            13 => new DualGenerator($release),
            14 => new EscapedRelationGenerator($release),
            15 => new JoinedTableGenerator($release),
            16 => new JsonTableGenerator($release),
            17 => new NestedRelationGenerator($release),
            18 => new OdbcJoinGenerator($release),
            19 => new TableListGenerator($release),
            20 => new TableReferenceGenerator($release),
            21 => new ParameterListGenerator($release),
            22 => new VariableDeclarationGenerator($release),
            23 => new TriggerTableGenerator($release),
            24 => new CachedTableGenerator($release),
            25 => new PreloadedTableGenerator($release),
            26 => new TableLockGenerator($release),
            27 => new MaintainedTableGenerator($release),
            28 => new CreateIndexGenerator($release),
            29 => new CreateTableGenerator($release),
            30 => new CreateTableLikeGenerator($release),
            31 => new ReferencesGenerator($release),
            32 => new InspectedTableGenerator($release),
            33 => new ShowFunctionStatusGenerator($release),
            34 => new ShowProcedureStatusGenerator($release),
            35 => new ShowColumnsGenerator($release),
            36 => new ShowDatabasesGenerator($release),
            37 => new ShowEventsGenerator($release),
            38 => new ShowKeysGenerator($release),
            39 => new ShowOpenTablesGenerator($release),
            40 => new ShowTableStatusGenerator($release),
            41 => new ShowTablesGenerator($release),
            42 => new ShowTriggersGenerator($release),
            43 => new ShowCharacterSetGenerator($release),
            44 => new ShowCollationGenerator($release),
            45 => new ShowStatusGenerator($release),
            46 => new ShowVariablesGenerator($release),
            47 => new AlterViewGenerator($release),
            default => throw new LogicException('Unreachable alternative.'),
        });
        $this->delegate = Generators::suchThat(
            static function (TableUse $value) use ($release): bool {
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
     * @return GeneratedValueSingle<TableUse>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<TableUse>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
