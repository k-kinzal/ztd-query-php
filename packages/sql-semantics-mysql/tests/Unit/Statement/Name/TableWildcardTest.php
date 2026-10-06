<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(TableWildcard::class)]
#[Small]
final class TableWildcardTest extends TestCase
{
    public function testRenderWritesTheTableQualifierAndTheStar(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new TableWildcard(new QualifiedName(new Name('t'))))->render($out);

        self::assertSame('t.*', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheDatabaseQualifierAndQuotesWhatNeedsIt(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new TableWildcard(new QualifiedName(new Name('t'), new Name('my db'))))->render($out);

        self::assertSame('`my db`.t.*', (new Lexical())->join($out->pieces()));
    }

    public function testLoweredWildcardKeepsTheQualifiersAsWritten(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $qualified = $lowering->names->wildcard($parser->parse('SELECT db.T.* FROM db.t')->find('table_wild')[0]);
        $plain = $lowering->names->wildcard($parser->parse('SELECT t.* FROM t')->find('table_wild')[0]);

        self::assertSame('T', $qualified->table->name->value);
        self::assertSame('db', $qualified->table->schema?->value);
        self::assertSame('t', $plain->table->name->value);
        self::assertNull($plain->table->schema);
    }

    public function testRejectsACatalogQualifier(): void
    {
        $this->expectExceptionMessage('A table is qualified by at most a database.');

        new TableWildcard(new QualifiedName(new Name('t'), new Name('db'), new Name('catalog')));
    }
}
