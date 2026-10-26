<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;

/**
 * Generates scalar and row-valued UPDATE assignments without a catalog.
 *
 * @implements ErisGenerator<Update>
 */
final class UpdateGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Update> */
    private ErisGenerator $delegate;

    /**
     * Composes the written operands through Eris generation and shrinking.
     */
    public function __construct(GrammarRelease $release)
    {
        $operands = Generators::associative(['column' => new ColumnNameGenerator($release), 'number' => Generators::choose(0, 10000), 'row' => Generators::bool()]);
        $this->delegate = Generators::map(
            static function (array $data): Update {
                if (!$data['column'] instanceof M\Name\ColumnName || !is_int($data['number']) || !is_bool($data['row'])) {
                    throw new LogicException('UPDATE operands require a column name, integer value and row flag.');
                }
                $column = new M\Name\ColumnUse($data['column']->column);
                $number = new M\Literal\NumberLiteral((string) $data['number']);
                $assignment = $data['row']
                    ? new M\Routine\Program\Write\DeferredColumnAssignment($column, new M\Expression\Row([$number, new M\Literal\NumberLiteral('2')]))
                    : new M\Dml\Assignment($column, $number);
                return new Update(null, false, false, [new M\Relation\TableReference(new QualifiedName(new Name('target')))], [$assignment]);
            },
            $operands,
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Update>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Update>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
