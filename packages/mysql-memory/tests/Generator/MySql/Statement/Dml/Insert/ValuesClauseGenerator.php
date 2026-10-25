<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClause;

/**
 * Composes ValuesClause values through Eris generation and shrinking.
 *
 * @implements ErisGenerator<ValuesClause>
 */
final class ValuesClauseGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<ValuesClause>
     */
    private ErisGenerator $delegate;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Expression> $expressions The one-column row values
     */
    public function __construct(ErisGenerator $expressions)
    {
        $this->delegate = Generators::map(
            static function (array $data): ValuesClause {
                if (!$data['first'] instanceof \SqlSemantics\Statement\Expression || !is_array($data['rest']) || !$data['keyword'] instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword) {
                    throw new LogicException('The row generators must produce an expression and a list of expressions.');
                }
                return new ValuesClause(array_map(static fn (\SqlSemantics\Statement\Expression $value): array => [$value], [$data['first'], ...$data['rest']]), keyword: $data['keyword']);
            },
            Generators::associative(['first' => $expressions, 'rest' => Generators::seq($expressions), 'keyword' => new ValuesKeywordGenerator()]),
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<ValuesClause>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ValuesClause>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
