<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Text\Json;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Text\Json\Attribute;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineAttributeOption;
use SqlSemantics\Platform\MySql\Statement\Table\Column\EngineAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexEngineAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption;
use SqlSemantics\Statement\Node;

#[CoversClass(Attribute::class)]
#[CoversClass(EngineAttribute::class)]
#[CoversClass(IndexEngineAttribute::class)]
#[CoversClass(EngineAttributeOption::class)]
#[CoversClass(TextOption::class)]
#[Small]
final class AttributeTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(Text): Node}>
     */
    public static function providerRoles(): iterable
    {
        yield 'column' => [static fn (Text $text): Node => new EngineAttribute($text)];
        yield 'secondary column' => [static fn (Text $text): Node => new EngineAttribute($text, true)];
        yield 'index' => [static fn (Text $text): Node => new IndexEngineAttribute($text)];
        yield 'secondary index' => [static fn (Text $text): Node => new IndexEngineAttribute($text, true)];
        yield 'storage' => [static fn (Text $text): Node => new EngineAttributeOption($text)];
        yield 'table' => [static fn (Text $text): Node => new TextOption(TextOptionKind::EngineAttribute, $text)];
        yield 'secondary table' => [static fn (Text $text): Node => new TextOption(TextOptionKind::SecondaryEngineAttribute, $text)];
    }

    /**
     * @param Closure(Text): Node $construct A public attribute constructor
     */
    #[DataProvider('providerRoles')]
    public function testCheckProtectsEveryAttributeRole(Closure $construct): void
    {
        $this->expectExceptionMessage('An engine attribute requires an empty string or a valid JSON document.');
        $construct(new Text('bad'));
    }

    /**
     * @param Closure(Text): Node $construct A public attribute constructor
     */
    #[DataProvider('providerRoles')]
    public function testCheckRejectsRadixLiteralSpelling(Closure $construct): void
    {
        $this->expectExceptionMessage('An engine attribute requires quoted text.');
        $construct(new Text('7b7d', Radix::Hexadecimal));
    }

    /**
     * @param Closure(Text): Node $construct A public attribute constructor
     */
    #[DataProvider('providerRoles')]
    public function testCheckAcceptsResetAndScalarJsonDocuments(Closure $construct): void
    {
        $output = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\MySql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::MySql847));
        $construct(new Text(''))->render($output);
        $construct(new Text('null'))->render($output);
        $construct(new Text('"value"'))->render($output);
        $rendered = (new \SqlSemantics\Rendering\Lexical())->join($output->pieces());
        self::assertStringContainsString("''", $rendered);
        self::assertStringContainsString("'null'", $rendered);
        self::assertStringContainsString("'\"value\"'", $rendered);
    }

    public function testFailureDistinguishesResetFromWhitespaceOnly(): void
    {
        self::assertNull(Attribute::failure(''));
        self::assertSame('The document is empty.', Attribute::failure(' ')?->reason);
        self::assertSame(1, Attribute::failure(' ')->position);
        self::assertNull(Attribute::failure('[1,{},true]'));
    }

    public function testOtherTextOptionsRetainTheirOwnDomain(): void
    {
        $text = new Text('not JSON');
        self::assertSame($text, (new TextOption(TextOptionKind::Comment, $text))->value);
    }
}
