<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Routine\LanguageRule;

#[CoversClass(LanguageRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class LanguageRuleTest extends TestCase
{
    #[DataProvider('providerBodies')]
    public function testRewriteSelectsTheLanguageFromTheRetainedBody(string $scope, string $body, ?string $language): void
    {
        $name = new TerminalOccurrence('IDENT', 10, [0], [$scope]);
        $content = new TerminalOccurrence($body, 11, [0, 1], [$scope, 'stored_routine_body']);
        $input = new TerminalSequence([$name, $content], productions: [new ProductionOccurrence(0, null, $scope, 0), new ProductionOccurrence(1, 0, 'stored_routine_body', 0)]);
        $result = (new LanguageRule())->rewrite($input);
        self::assertSame($language === null ? ['IDENT', $body] : ['IDENT', 'LANGUAGE_SYM', $language, $body], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($content, $result->terminals[count($result->terminals) - 1]);
        self::assertCount(count($result->terminals), array_unique(array_column($result->terminals, 'id')));
        self::assertSame($result, (new LanguageRule())->rewrite($result));
    }

    /**
     * @return list<array{string, string, string|null}>
     */
    public static function providerBodies(): array
    {
        return [['sp_tail', 'AS', 'EXTERNAL_ROUTINE_LANGUAGE'], ['sf_tail', 'AS', 'EXTERNAL_ROUTINE_LANGUAGE'], ['sp_tail', 'BEGIN_SYM', 'SQL_SYM'], ['sf_tail', 'RETURN_SYM', 'SQL_SYM'], ['ordinary', 'AS', null]];
    }

    public function testRewritePreservesAnEmptyBody(): void
    {
        $input = new TerminalSequence([], productions: [new ProductionOccurrence(0, null, 'stored_routine_body', 0)]);
        self::assertSame($input, (new LanguageRule())->rewrite($input));
    }
}
