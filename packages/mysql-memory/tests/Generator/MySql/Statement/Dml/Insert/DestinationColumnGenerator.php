<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\DestinationColumn;

/**
 * Composes DestinationColumn values through Eris generation and shrinking.
 *
 * @implements ErisGenerator<DestinationColumn>
 */
final class DestinationColumnGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<DestinationColumn>
     */
    private ErisGenerator $delegate;

    /**
     * Selects a declared column, or constructs a name whose table is unresolved.
     */
    public function __construct(\SqlSemantics\Statement\Reference\Table\TableResolution $table)
    {
        $this->delegate = $table instanceof \SqlSemantics\Statement\Reference\Table\DeclaredTable
            ? Generators::map(static fn (\SqlSemantics\Statement\Declaration\Column $column): DestinationColumn => new DestinationColumn($column->name, $table, $column), Generators::elements($table->table->columns))
            : Generators::map(static fn (int $id): DestinationColumn => new DestinationColumn(new \SqlSemantics\Statement\Identifier\Name('column_' . $id), $table), Generators::choose(0, 1000));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<DestinationColumn>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DestinationColumn>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
