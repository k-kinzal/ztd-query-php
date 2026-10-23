<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Definition;

use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Choices;

/**
 * Finite option domains and generated casing execute without recovering source SQL.
 */
#[CoversNothing]
#[Medium]
final class AlterChoicesProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, AlgorithmOption|LockOption}>
     */
    public static function providerOptions(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach ([false, true] as $lock) {
                $names = $lock ? ['NONE', 'SHARED', 'EXCLUSIVE'] : ['COPY', 'INPLACE', ...(str_starts_with($release, '5.') ? [] : ['INSTANT'])];
                foreach ([null, ...$names, ...(str_starts_with($release, '5.') ? ['DEFAULT'] : [])] as $name) {
                    for ($seed = 0; $seed < 5; $seed++) {
                        $choices = new Choices('alter-choice:' . $release . ':' . $seed);
                        $spelling = $name === null ? null : new Name(implode('', array_map(static fn (string $letter): string => $choices->one([$letter, strtolower($letter)]), str_split($name))));
                        yield $release . ':' . ($lock ? 'lock' : 'algorithm') . ':' . ($name ?? 'keyword') . ':' . $seed => [$release, $lock ? new LockOption($spelling) : new AlgorithmOption($spelling)];
                    }
                }
            }
        }
    }

    /**
     * All known algorithms permit an unchanged table; all known locks permit adding its secondary index.
     * The fixture supplies the table and integer column independently of the constructed statement.
     */
    #[DataProvider('providerOptions')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesGeneratedOptionUsesItsConstructedDomain(string $release, AlgorithmOption|LockOption $option): void
    {
        $session = (new Instance($release))->connect();
        $session->execute('CREATE DATABASE d');
        $session->execute('CREATE TABLE d.t(a INT)');
        $commands = [$option];
        if ($option instanceof LockOption) {
            $commands[] = new AddConstraint(new IndexDefinition(IndexKind::Index, [new ColumnPart(new Name('a'))], new ColumnName(new Name('i'))));
        }
        $statement = new AlterTable(new QualifiedName(new Name('t'), new Name('d')), $commands);
        $session->text = 'unrelated invalid SQL';

        self::assertInstanceOf(Completion::class, (new Execution())->runIn($session, $statement));
    }
}
