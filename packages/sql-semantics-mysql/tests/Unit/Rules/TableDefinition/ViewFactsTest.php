<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ViewFacts;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ViewFacts::class)]
#[Medium]
final class ViewFactsTest extends TestCase
{
    public function testDeriveAnswersTheDeclarationOfTheView(): void
    {
        $create = (new Inspection())->inspect('CREATE VIEW v AS SELECT 1 AS a');
        $statement = $create->statement;
        self::assertInstanceOf(CreateView::class, $statement);
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $table = (new ViewFacts())->derive($statement->definition, $derivation);

        self::assertSame('a', $table->columns[0]->name->value);
    }
}
