<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Definitions;
use Requirements\Model\Item;
use Requirements\Model\Source;

#[CoversClass(Definitions::class)]
#[UsesClass(Item::class)]
#[UsesClass(Source::class)]
#[Small]
final class DefinitionsTest extends TestCase
{
    public function testHoldsItemsSourcesAndFiles(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $item = new Item('SPEC-001', 'specification', 'The parser shall require a leading letter.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', '/project/definition.yaml', []);
        $definitions = new Definitions(['SPEC-001' => $item], ['manual' => $source], ['/project/a.yaml', '/project/definition.yaml']);
        self::assertSame(['SPEC-001' => $item], $definitions->items);
        self::assertSame(['manual' => $source], $definitions->sources);
        self::assertSame(['/project/a.yaml', '/project/definition.yaml'], $definitions->files);
    }

    public function testHoldsEmptyDefinitions(): void
    {
        $definitions = new Definitions([], [], []);
        self::assertSame([], $definitions->items);
        self::assertSame([], $definitions->sources);
        self::assertSame([], $definitions->files);
    }
}
