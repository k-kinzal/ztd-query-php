<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\FamilyDefinition;
use SqlFaker\Contract\GrammarSnapshotBuilder;

#[CoversClass(GrammarSnapshotBuilder::class)]
final class GrammarSnapshotBuilderTest extends TestCase
{
    public function testBuildNormalizesCompiledGrammarObjects(): void
    {
        $nonTerminal = new class ('expr') {
            public function __construct(public string $value)
            {
            }
        };

        $terminal = new class ('SELECT') {
            public function __construct(public string $value)
            {
            }
        };

        $grammar = new class ($nonTerminal, $terminal) {
            public string $startSymbol = 'stmt';

            /** @var array<string, object> */
            public array $ruleMap;

            public function __construct(object $nonTerminal, object $terminal)
            {
                $this->ruleMap = [
                    'stmt' => new class ($nonTerminal, $terminal) {
                        /** @var list<object> */
                        public array $alternatives;

                        public function __construct(object $nonTerminal, object $terminal)
                        {
                            $this->alternatives = [
                                new class ($terminal, $nonTerminal) {
                                    /** @var list<object> */
                                    public array $symbols;

                                    public function __construct(object $terminal, object $nonTerminal)
                                    {
                                        $this->symbols = [$terminal, $nonTerminal];
                                    }
                                },
                            ];
                        }
                    },
                ];
            }
        };

        $snapshot = (new GrammarSnapshotBuilder())->build(
            'mysql',
            $grammar,
            ['stmt'],
            [new FamilyDefinition('family.id', 'family', 'contract', ['stmt'])],
            $nonTerminal::class,
        );

        self::assertSame('mysql', $snapshot->dialect);
        self::assertSame('stmt', $snapshot->startRule);
        self::assertSame(['stmt'], $snapshot->entryRules);
        self::assertSame(['t:SELECT', 'nt:expr'], $snapshot->rules['stmt']->alternatives[0]->sequence());
        self::assertSame(['stmt'], $snapshot->familyAnchors['family.id']);
    }

    public function testBuildRejectsInvalidGrammarShape(): void
    {
        $grammar = new class () {
            public int $startSymbol = 1;

            /** @var array<string, object> */
            public array $ruleMap = [];
        };

        $this->expectException(InvalidArgumentException::class);
        (new GrammarSnapshotBuilder())->build('mysql', $grammar, [], [], \stdClass::class);
    }
}
