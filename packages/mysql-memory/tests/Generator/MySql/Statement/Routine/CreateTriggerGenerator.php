<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Generates trigger headers, preserving explicit matching or distinct schemas.
 *
 * @implements ErisGenerator<CreateTrigger>
 */
final class CreateTriggerGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CreateTrigger> */
    private ErisGenerator $delegate;

    /**
     * Composes the written operands through Eris generation and shrinking.
     */
    public function __construct()
    {
        $operands = Generators::associative(['number' => Generators::choose(0, 10000), 'mismatch' => Generators::bool(), 'time' => Generators::elements(...M\Routine\Trigger\TriggerTime::cases()), 'event' => Generators::elements(...M\Routine\Trigger\TriggerEvent::cases())]);
        $this->delegate = Generators::map(
            static function (array $data): CreateTrigger {
                if (!is_int($data['number']) || !is_bool($data['mismatch']) || !$data['time'] instanceof M\Routine\Trigger\TriggerTime || !$data['event'] instanceof M\Routine\Trigger\TriggerEvent) {
                    throw new LogicException('Trigger operands require a name suffix, schema flag, time and event.');
                }
                return new CreateTrigger(new QualifiedName(new Name('tr' . $data['number']), new Name($data['mismatch'] ? 'other' : 'pbt')), $data['time'], $data['event'], new M\Routine\Trigger\TriggerTable(new QualifiedName(new Name('target'), new Name('pbt'))), new M\Routine\Program\Block([], []));
            },
            $operands,
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<CreateTrigger>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CreateTrigger>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
