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

    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testJoinConvertsEveryKnownElementInOrder(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return implode("-",[1,"a",true,null]);}');
        self::assertSame('1-a-1-', $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testPartialJoinsTheHeadExactlyForAnEmptySourceAndKeepsItBeforeTheResidualOtherwise(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(array $x){return implode(",",["id","name",...$x]);}');
        $values = array_map(static fn (\Deriver\Result\Alternative $outcome): \Deriver\Value\Term => $outcome->values['return'], $result->normalOutcomes);
        self::assertCount(2, $values);
        self::assertSame('id,name', $values[0]->native());
        self::assertSame('concat', $values[1]->kind);
        self::assertSame('id,name,', $values[1]->operands[0]->native());
        self::assertSame(['UNSUPPORTED_MODEL_CASE'], array_column($result->frontiers, 'code'));
        self::assertSame('implode', $result->frontiers[0]->operation);
        self::assertTrue($result->exceptionalOutcomes[0]->exception->attributes['uncertain'] ?? false);
    }

    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testPartialRunsHeadConversionsBeforeTheUnknownSource(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class T{function __toString(){throw new LogicException;}} function target(array $x){try{return implode(",",[new T,...$x]);}catch(LogicException $e){return "caught";}}');
        self::assertSame(['"caught"'], array_values(array_unique(array_map(static fn (\Deriver\Result\Alternative $outcome): string => (string) json_encode($outcome->values['return']->native()), $result->normalOutcomes))));
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testPartialKeepsConfidentialSources(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(array $x){return implode(",",["id",...$x]);}');
        $result = $session->derive(new \Deriver\Query\ReturnQuery('target', scope: \Deriver\Query\QueryScope::fromEntrypoints([new \Deriver\Project\EntryPoint('target', [new \Deriver\Value\Term('parameter', 'x', attributes: ['type' => 'array'], secret: true)])])));
        self::assertNotEmpty($result->normalOutcomes);
        self::assertSame([true], array_values(array_unique(array_map(static fn (\Deriver\Result\Alternative $outcome): bool => $outcome->values['return']->isSecret(), $result->normalOutcomes))));
    }
}
