<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operation;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class StringJoiningTest extends TestCase
{
    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testApplyPreservesStringConversionEffects(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{public int $n=0;function __toString(){return (string)++$this->n;}}function target(){$b=new B;return [implode(",",[$b,$b]),$b->n];}');
        self::assertSame(['1,2',2], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
}
