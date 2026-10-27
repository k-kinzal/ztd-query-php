<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedLoadAssignments values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadAssignments>
 */
final class RefusedLoadAssignmentsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadAssignments> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat> $format Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadLock|null> $lock Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput> $input Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling|null> $duplicates Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CharsetName|null>|null $charset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $compression Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $rowTag Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOption>>|null $fields Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOption>>|null $lines Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $ignored Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Name\ColumnUse|\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable>>|null $columns Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment>>|null $assignments Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions|null>|null $bulk Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $format, ErisGenerator $lock, ErisGenerator $input, ErisGenerator $duplicates, ErisGenerator $table, ?ErisGenerator $charset = null, ?ErisGenerator $compression = null, ?ErisGenerator $rowTag = null, ?ErisGenerator $fields = null, ?ErisGenerator $lines = null, ?ErisGenerator $ignored = null, ?ErisGenerator $columns = null, ?ErisGenerator $assignments = null, ?ErisGenerator $bulk = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadAssignments {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadAssignments(format: $parts[0], lock: $parts[1], input: $parts[2], duplicates: $parts[3], table: $parts[4], charset: $parts[5], compression: $parts[6], rowTag: $parts[7], fields: $parts[8], lines: $parts[9], ignored: $parts[10], columns: $parts[11], assignments: $parts[12], bulk: $parts[13], inputSource: $parts[14]);
        }, \Tests\Generator\Operands::tuple(
            $format,
            $lock,
            $input,
            $duplicates,
            $table,
            $charset ?? Generators::constant(null),
            $compression ?? Generators::constant(null),
            $rowTag ?? Generators::constant(null),
            $fields ?? Generators::constant([]),
            $lines ?? Generators::constant([]),
            $ignored ?? Generators::constant(null),
            $columns ?? Generators::constant([]),
            $assignments ?? Generators::constant([]),
            $bulk ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadAssignments>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\RefusedLoadAssignments' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedLoadAssignmentsGenerator(
            format: \Tests\Generator\MySql\Statement\Dml\Load\LoadFormatGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat::Data]),
            lock: Generators::constant(null),
            input: new \Tests\Generator\MySql\Statement\Dml\Load\LoadInputGenerator(
                local: Generators::constant(true),
                source: \Tests\Generator\MySql\Statement\Dml\Load\LoadSourceGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::Infile]),
                file: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('f'),
                    radix: Generators::constant(null)
                ),
                count: Generators::constant(null),
                primaryKeyOrder: Generators::constant(false),
                countWord: Generators::constant(null)
            ),
            duplicates: Generators::constant(null),
            table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $suffix): string => 't' . $suffix, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                alias: Generators::constant(null),
                partitions: Generators::constant([]),
                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
            ),
            charset: Generators::constant(null),
            compression: Generators::constant(null),
            rowTag: Generators::constant(null),
            fields: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Dml\Load\FieldOptionGenerator(
                    kind: \Tests\Generator\MySql\Statement\Dml\Load\FieldOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind::Terminated]),
                    text: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant(''),
                        radix: Generators::constant(null)
                    )
                )
            ),
            lines: Generators::constant([]),
            ignored: Generators::constant(null),
            columns: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $suffix): string => 'v' . $suffix, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                )
            ),
            assignments: Generators::constant([]),
            bulk: Generators::constant(null)
        )];
    }

}
