<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\AllForeignTables;
use SqlSemantics\Model\Definition\Foreign\ExcludeForeignTables;
use SqlSemantics\Model\Definition\Foreign\ImportOnlyTables;
use SqlSemantics\Model\Statement\Definition\PostgreSql\ImportForeignSchemaStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\ForeignImports::class)]
#[Medium]
final class ForeignImportsTest extends TestCase
{
    /**
     * @param class-string<AllForeignTables|ImportOnlyTables|ExcludeForeignTables> $selectionClass
     */
    #[TestWith(['', AllForeignTables::class])]
    #[TestWith([' LIMIT TO (users, logs)', ImportOnlyTables::class])]
    #[TestWith([' EXCEPT (users, logs)', ExcludeForeignTables::class])]
    public function testBindClassifiesAllThreeRemoteSelections(string $selection, string $selectionClass): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $statement = (new Binder($schema))->bind('IMPORT FOREIGN SCHEMA "External"' . $selection . ' FROM SERVER "Remote" INTO "Local"');
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        self::assertSame($selectionClass, $statement->selection::class);
        self::assertSame('External', $statement->remoteSchema);
        self::assertSame('Remote', $statement->server);
        self::assertSame('Local', $statement->localSchema);
        self::assertSame([], $statement->diagnostics);
        self::assertSame([], $schema->tables);
        self::assertSame('IMPORT', $statement->kind->value);
        self::assertSame($statement->toString(), (new Binder($schema))->bind($statement->toString())->toString());
    }

    public function testBindRetainsOptionsOrderIncludingRepeatedWrapperOptionNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app OPTIONS (option 'first', option 'second')");
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        self::assertCount(2, $statement->options);
        self::assertSame('option', $statement->options[0]->name);
        self::assertSame("'first'", $statement->options[0]->value->text);
        self::assertSame("'second'", $statement->options[1]->value->text);
    }

}
