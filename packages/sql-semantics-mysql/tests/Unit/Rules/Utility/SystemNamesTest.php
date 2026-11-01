<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Rules\Utility\SystemNames;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SystemNames::class)]
#[Medium]
final class SystemNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function providerOccurrences(): iterable
    {
        yield 'legacy read' => ['mysql-5.7.44', false, 'variable_name'];
        yield 'legacy target' => ['mysql-5.7.44', true, 'variable_name'];
        yield 'modern read' => ['mysql-8.4.7', false, 'component.variable_name'];
        yield 'modern target' => ['mysql-8.4.7', true, 'component.variable_name'];
    }

    #[DataProvider('providerOccurrences')]
    public function testStructuredRetainsTheOccurrenceRole(string $release, bool $assigned, string $name): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, $release))->context());
        (new SystemNames())->structured(new Name('variable_name'), new Name('component'), $assigned, $derivation);

        self::assertEquals([new UnknownSystemVariable($name, $assigned, true)], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesAQualifiedSystemName(): void
    {
        $output = new Output(new Codec(GrammarRelease::MySql847));
        (new SystemNames())->render(new Name('variable_name'), VariableScope::Persist, new Name('component'), $output);

        self::assertSame('@@PERSIST.`component`.variable_name', (new Lexical())->join($output->pieces()));
    }
}
