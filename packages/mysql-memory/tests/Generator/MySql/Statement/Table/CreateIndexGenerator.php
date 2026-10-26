<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;

/**
 * Generates complete column indexes, including the reserved PRIMARY name.
 *
 * @implements ErisGenerator<CreateIndex>
 */
final class CreateIndexGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CreateIndex> */
    private ErisGenerator $delegate;

    /**
     * Composes the written operands through Eris generation and shrinking.
     */
    public function __construct(GrammarRelease $release)
    {
        $operands = Generators::associative(['column' => new ColumnNameGenerator($release), 'invalid' => Generators::bool()]);
        $this->delegate = Generators::map(
            static function (array $data): CreateIndex {
                if (!$data['column'] instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnName || !is_bool($data['invalid'])) {
                    throw new LogicException('Index operands require a column name and a condition flag.');
                }
                return new CreateIndex(new Name($data['invalid'] ? 'PRIMARY' : 'idx'), new QualifiedName(new Name('target')), [new ColumnPart($data['column']->column)]);
            },
            $operands,
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<CreateIndex>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CreateIndex>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
