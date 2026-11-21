<?php

declare(strict_types=1);

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

require $argv[1];
$root = $argv[2];
$overlay = $argv[3] ?? '';
$dialect = $argv[4] ?? 'sqlite';
$platform = 'SqlSemantics\\Platform\\' . match ($dialect) {
    'mysql' => 'MySql',
    'postgres' => 'PostgreSql',
    'sqlite' => 'Sqlite',
};
$prefix = $platform . '\\Ir\\';
$operands = ['Arithmetic', 'ColumnReference', 'IntegerLiteral', 'NullLiteral', 'Parameter'];
$properties = [
    'Arithmetic' => ['operator' => ['ArithmeticOperator'], 'left' => $operands, 'right' => $operands],
    'ArithmeticOperator' => [],
    'ColumnReference' => ['slot' => ['int']],
    'Identifier' => ['value' => ['string']],
    'IntegerLiteral' => ['decimal' => ['string']],
    'NullLiteral' => [],
    'Parameter' => ['slot' => ['int'], 'label' => ['string']],
    'ResultColumn' => ['expression' => $operands, 'name' => ['Identifier']],
    'Select' => ['columns' => ['array'], 'source' => ['?TableSource']],
    'TableSource' => ['columns' => ['array'], 'schema' => ['Identifier'], 'table' => ['Identifier']],
];
$methods = [
    'Arithmetic' => ['__construct'], 'ArithmeticOperator' => [],
    'ColumnReference' => ['__construct'], 'Identifier' => ['__construct', 'quoted', 'key'],
    'IntegerLiteral' => ['__construct'], 'NullLiteral' => [], 'Parameter' => ['__construct'],
    'ResultColumn' => ['__construct'], 'Select' => ['__construct', 'profileId', 'toString'],
    'TableSource' => ['__construct'],
];
if ($dialect !== 'sqlite') {
    unset($properties['Parameter'], $methods['Parameter']);
    $properties['BooleanLiteral'] = ['value' => ['bool']];
    $methods['BooleanLiteral'] = ['__construct'];
    $properties['Arithmetic']['left'] = $properties['Arithmetic']['right'] = ['Arithmetic', 'ColumnReference', 'IntegerLiteral'];
    $properties['ResultColumn']['expression'] = ['Arithmetic', 'BooleanLiteral', 'ColumnReference', 'IntegerLiteral', 'NullLiteral'];
    $properties['Select']['release'] = [$platform . '\\Model\\Contract\\GrammarRelease'];
}
if ($dialect === 'postgres') {
    $methods['Identifier'] = ['__construct', 'quoted'];
    $properties['TableSource']['only'] = ['bool'];
}
$internal = ['ExpressionLowering', 'SelectBounds', 'SelectLowering', 'SelectSql', 'SourceBinding'];
$enumCases = ['ArithmeticOperator' => ['Add' => '+', 'Subtract' => '-', 'Multiply' => '*']];
if ($dialect === 'sqlite') {
    $predicates = [...array_slice($operands, 0, 2), 'IntegerLiteral', 'NullLiteral', 'Parameter', 'Predicate\\Comparison', 'Predicate\\Logical'];
    $nullablePredicates = [...$predicates, 'null'];
    $properties['ColumnReference']['source'] = ['int'];
    $properties['Select']['joins'] = ['array'];
    $properties['Select']['orderBy'] = ['array'];
    $properties['Select']['where'] = $nullablePredicates;
    $properties['From\\Join'] = ['kind' => ['From\\JoinKind'], 'on' => $nullablePredicates, 'source' => ['TableSource']];
    $properties['Ordering\\Order'] = ['descending' => ['bool'], 'nullsFirst' => ['bool'], 'position' => ['int']];
    $properties['Predicate\\Comparison'] = ['operator' => ['Predicate\\ComparisonOperator'], 'left' => $operands, 'right' => $operands];
    $properties['Predicate\\Logical'] = ['operator' => ['Predicate\\LogicalOperator'], 'left' => $predicates, 'right' => $predicates];
    $enumCases['From\\JoinKind'] = ['Inner' => 'INNER', 'Cross' => 'CROSS', 'Left' => 'LEFT', 'Right' => 'RIGHT', 'Full' => 'FULL'];
    $enumCases['Predicate\\ComparisonOperator'] = [
        'Equal' => '=', 'NotEqual' => '<>', 'Less' => '<', 'LessOrEqual' => '<=',
        'Greater' => '>', 'GreaterOrEqual' => '>=', 'Is' => 'IS', 'IsNot' => 'IS NOT',
    ];
    $enumCases['Predicate\\LogicalOperator'] = ['And' => 'AND', 'Or' => 'OR'];
    foreach (['From\\JoinKind', 'Predicate\\ComparisonOperator', 'Predicate\\LogicalOperator'] as $enum) {
        $methods[$enum] = $properties[$enum] = [];
    }
    foreach (['From\\Join', 'Ordering\\Order', 'Predicate\\Comparison', 'Predicate\\Logical'] as $value) {
        $methods[$value] = ['__construct'];
    }
    array_push($internal, 'From\\SourceDeclarations', 'From\\QueryClauses');
}
$snapshot = $platform . '\\Model\\Statement\\Snapshot';
$allowed = [
    $snapshot, 'SqlSemantics\\SemanticsIR',
    $platform . '\\Model\\Diagnostic\\Check',
    $platform . '\\Model\\Statement\\Identifier\\Comparison',
    $platform . '\\Model\\Contract\\GrammarRelease',
    ...array_map(static fn ($short) => $prefix . $short, [...array_keys($properties), 'SelectBounds', 'SelectSql']),
];
$pureFunctions = ['strlen','str_contains','preg_match','str_replace','ltrim','strcmp','count','isset','implode','array_pop','array_push','strpbrk'];

function requireShape(bool $condition, string $detail): void
{
    if (!$condition) {
        throw new RuntimeException($detail);
    }
}

function types($type, string $prefix, string $self): array
{
    if ($type instanceof PhpParser\Node\NullableType) {
        return ['?' . types($type->type, $prefix, $self)[0]];
    }
    $members = $type instanceof UnionType ? $type->types : [$type];
    $names = array_map(static fn ($member) => $member->toString() === 'self' ? $self : str_replace($prefix, '', $member->toString()), $members);
    sort($names);
    return $names;
}

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$traverser = new NodeTraverser(new NameResolver());
$finder = new NodeFinder();
try {
    $package = 'sql-semantics-' . $dialect;
    $facadePath = $package . '/src/Semantics.php';
    $patchedFacade = $overlay === '' ? '' : $overlay . '/' . $facadePath;
    $facadeFile = is_file($patchedFacade) ? $patchedFacade : $root . '/packages/' . $facadePath;
    $facadeAst = $traverser->traverse($parser->parse(file_get_contents($facadeFile)));
    $facade = $finder->findFirstInstanceOf($facadeAst, Class_::class);
    requireShape($facade instanceof Class_ && $facade->isFinal() && $facade->extends === null
        && $facade->namespacedName->toString() === $platform . '\\Semantics', 'The complete façade must be a final dialect class');
    requireShape(array_map(static fn ($name) => $name->toString(), $facade->implements) === ['SqlSemantics\\Analyzer'], 'Unexpected complete façade interface');
    requireShape($facade->getMethod('ir') === null, 'The façade must publish IR directly');
    $printer = new Standard();
    $routes = [
        'analyze' => [$prefix . 'Select', 'return (new \\' . $prefix . 'SelectLowering())->lower($this->inspect($sql, $context));'],
        'analyzeAll' => ['array', '$declarations = $this->context($context); $results = [];'
            . 'foreach ($this->split($sql) as $statement) { $results[] = $this->analyze($statement, $declarations); } return $results;'],
    ];
    foreach ($routes as $name => [$return, $body]) {
        $method = $facade->getMethod($name);
        requireShape($method !== null && $method->isPublic() && !$method->isStatic()
            && $method->returnType?->toString() === $return, "Publication signature differs in $name");
        requireShape($printer->prettyPrint($method->stmts) === $printer->prettyPrint($parser->parse('<?php ' . $body)), "Publication body differs in $name");
    }
    $primitives = json_decode(file_get_contents(__DIR__ . '/value-primitives.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($primitives['artifacts'][$dialect] as $path => $approved) {
        $patched = $overlay === '' ? '' : $overlay . '/' . $package . '/' . $path;
        $file = is_file($patched) ? $patched : $root . '/packages/' . $package . '/' . $path;
        requireShape(is_file($file) && hash_file('sha256', $file) === $approved, "Value primitive differs from the recorded baseline: $path");
    }
    $base = $package . '/src/Ir';
    $directory = $root . '/packages/' . $base . '/';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    $observed = [];
    foreach ($files as $entry) {
        if (!$entry->isFile() || $entry->getExtension() !== 'php') {
            continue;
        }
        $file = $entry->getPathname();
        $relative = substr($file, strlen($directory));
        $short = str_replace('/', '\\', substr($relative, 0, -4));
        $patched = $overlay === '' ? '' : $overlay . '/' . $base . '/' . $relative;
        $ast = $traverser->traverse($parser->parse(file_get_contents(is_file($patched) ? $patched : $file)));
        foreach ($ast as $statement) {
            requireShape($statement instanceof Stmt\Declare_ || $statement instanceof Stmt\Namespace_, "Executable code outside a namespace in $short");
            if ($statement instanceof Stmt\Declare_) {
                requireShape($statement->stmts === null && count($statement->declares) === 1
                    && $statement->declares[0]->key->toString() === 'strict_types'
                    && $statement->declares[0]->value instanceof PhpParser\Node\Scalar\Int_
                    && $statement->declares[0]->value->value === 1, "Unexpected PHP execution directive in $short");
            }
            if ($statement instanceof Stmt\Namespace_) {
                foreach ($statement->stmts as $member) {
                    requireShape($member instanceof Stmt\Use_ || $member instanceof ClassLike || $member instanceof Stmt\Nop, "Executable namespace member in $short");
                }
            }
        }
        $classes = $finder->findInstanceOf($ast, ClassLike::class);
        requireShape(count($classes) === 1 && $classes[0]->namespacedName->toString() === $prefix . $short, "Unexpected declared type in $short");
        $class = $classes[0];
        $observed[] = $short;
        if (in_array($short, $internal, true)) {
            requireShape($class instanceof Class_ && $class->isFinal() && $class->extends === null, "Open IR collaborator $short");
            $interfaces = array_map(static fn ($interface) => $interface->toString(), $class->implements);
            requireShape($interfaces === [], "Unexpected collaborator interface in $short");
            if ($short === 'SelectLowering') {
                $publicMethods = array_values(array_map(
                    static fn ($method) => $method->name->toString(),
                    array_filter($class->getMethods(), static fn ($method) => $method->isPublic()),
                ));
                requireShape($publicMethods === ['lower'], 'Unexpected projection lowering entry point');
                requireShape($class->getMethod('lower')?->returnType?->toString() === $prefix . 'Select', 'Projection lowering return role differs');
            }
        }
        if (in_array($short, $internal, true) && $short !== 'SelectBounds' && $short !== 'SelectSql') {
            continue;
        }
        foreach ($finder->findInstanceOf($ast, Name\FullyQualified::class) as $name) {
            requireShape(in_array($name->toString(), $allowed, true), "Unexpected dependency in $short: $name");
        }
        foreach ($finder->findInstanceOf($ast, Expr\FuncCall::class) as $call) {
            requireShape($call->name instanceof Name && in_array($call->name->toString(), $pureFunctions, true), "Unexpected function in $short");
        }
        $danger = $finder->findFirst($ast, static fn ($node) => $node instanceof Expr\Eval_ || $node instanceof Expr\Include_
            || $node instanceof Expr\ShellExec || $node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction
            || $node instanceof Expr\AssignRef || $node instanceof PhpParser\Node\ArrayItem && $node->byRef || $node instanceof Expr\Print_ || $node instanceof Expr\Exit_ || $node instanceof Stmt\Echo_
            || $node instanceof Expr\Variable && (!is_string($node->name) || str_starts_with($node->name, '_') || $node->name === 'GLOBALS')
            || $node instanceof Expr\New_ && !$node->class instanceof Name
            || $node instanceof Expr\StaticCall && (!$node->class instanceof Name || !$node->name instanceof Identifier)
            || $node instanceof Expr\MethodCall && !$node->name instanceof Identifier
            || $node instanceof Expr\PropertyFetch && !$node->name instanceof Identifier);
        requireShape($danger === null, "Unexpected dynamic PHP in $short");
        if (!isset($properties[$short])) {
            requireShape($class instanceof Class_ && $class->isFinal() && $class->getProperties() === [], "Stateful SELECT collaborator $short");
            continue;
        }
        $actual = [];
        foreach ($class->getProperties() as $property) {
            requireShape($property->isReadonly() && !$property->isStatic() && $property->type !== null, "Writable or untyped property in $short");
            foreach ($property->props as $prop) {
                $actual[$prop->name->toString()] = types($property->type, $prefix, $short);
            }
        }
        $public = [];
        foreach ($class->getMethods() as $method) {
            requireShape($method->isPublic() && !$method->isStatic(), "Unexpected method in $short");
            $public[] = $method->name->toString();
            if ($method->name->toString() !== '__construct') {
                continue;
            }
            foreach ($method->params as $param) {
                if ($param->flags !== 0) {
                    requireShape(($param->flags & Class_::MODIFIER_READONLY) !== 0, "Writable promotion in $short");
                    $actual[$param->var->name] = types($param->type, $prefix, $short);
                }
            }
        }
        ksort($actual);
        $expected = $properties[$short];
        ksort($expected);
        requireShape($actual === $expected, "Property domain differs in $short");
        requireShape($methods[$short] === $public, "Publication paths differ in $short");
        if (isset($enumCases[$short])) {
            requireShape($class instanceof Enum_ && $class->scalarType?->toString() === 'string', 'Arithmetic operators must be a string enum');
            $operators = [];
            foreach ($class->stmts as $case) {
                requireShape($case instanceof Stmt\EnumCase && $case->expr instanceof String_, 'An arithmetic operator must be a literal case');
                $operators[$case->name->toString()] = $case->expr->value;
            }
            requireShape($operators === $enumCases[$short], "Operator meanings differ in $short");
            continue;
        }
        requireShape($class instanceof Class_ && $class->isFinal() && $class->extends === null, "Open value class $short");
        requireShape(array_map(static fn ($name) => $name->toString(), $class->implements) === ($short === 'Select' ? ['SqlSemantics\\SemanticsIR'] : []), "Interfaces differ in $short");
        $traits = $finder->findInstanceOf($class->stmts, TraitUse::class);
        requireShape(count($traits) === 1 && count($traits[0]->traits) === 1 && $traits[0]->traits[0]->toString() === $snapshot && $traits[0]->adaptations === [], "Snapshot paths differ in $short");
    }
    requireShape(array_diff([...array_keys($properties), ...$internal], $observed) === [] && count($observed) === count($properties) + count($internal), 'Closed IR inventory differs');
    sort($observed);
    echo json_encode(['status' => 'verified', 'types' => $observed]) . "\n";
} catch (RuntimeException $failure) {
    echo json_encode(['status' => 'shape-mismatch', 'message' => $failure->getMessage()]) . "\n";
    exit(1);
}
