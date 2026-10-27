<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword;

/**
 * Selects either grammar spelling through Eris.
 *
 * @implements ErisGenerator<ValuesKeyword>
 */
final class ValuesKeywordGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<ValuesKeyword>
     */
    private ErisGenerator $delegate;

    /**
     * Includes VALUE and VALUES without changing their execution semantics.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(ValuesKeyword::cases());
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<ValuesKeyword>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ValuesKeyword>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<ValuesKeyword> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<ValuesKeyword>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

}
