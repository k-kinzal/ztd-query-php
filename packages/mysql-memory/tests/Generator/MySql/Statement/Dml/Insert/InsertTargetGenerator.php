<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertTarget;

/**
 * Composes InsertTarget values through Eris generation and shrinking.
 *
 * @implements ErisGenerator<InsertTarget>
 */
final class InsertTargetGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<InsertTarget>
     */
    private ErisGenerator $delegate;

    /**
     * Generates a destination against the supplied definition, or an absent table.
     */
    public function __construct(?\SqlSemantics\Statement\Declaration\Table $table = null)
    {
        if ($table !== null) {
            $resolution = new \SqlSemantics\Statement\Reference\Table\DeclaredTable($table);
            $this->delegate = Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Dml\Insert\DestinationColumn $column): InsertTarget => new InsertTarget($table->name, $resolution, [$column]), new DestinationColumnGenerator($resolution));
            return;
        }
        $this->delegate = Generators::map(static function (int $id): InsertTarget {
            $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('target_' . $id));
            return new InsertTarget($name, new \SqlSemantics\Statement\Reference\Table\UndeclaredTable(new \SqlSemantics\Statement\Reference\Missing\UndeclaredRelation($name)));
        }, Generators::choose(0, 1000));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<InsertTarget>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<InsertTarget>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
