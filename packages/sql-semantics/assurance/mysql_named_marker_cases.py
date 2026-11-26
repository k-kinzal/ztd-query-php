"""Independent projected named input, native binding order and typed result model."""

from itertools import product
from random import Random

from run import Failure, require
from run_server import same
from run_server_mysql_ansi_projection import base, local, select
from run_server_mysql_unsigned_parameter import LEGACY, MAXIMUM, mathematical, wire
from run_server_mysql_with_projection import read, with_scope

SIGNED = "SIGNED"
UNSIGNED = "UNSIGNED"


def marker(position, kind, name=None):
    if name is not None:
        return ["parameter", position, kind, name]
    return ["parameter", position] if kind == SIGNED else ["parameter", position, UNSIGNED]


def cast(operand):
    return operand[2] if len(operand) >= 3 else SIGNED


def key(operand):
    return operand[3] if len(operand) == 4 else None


def scan(tag, mode, entries):
    columns = [[marker(position, kind, name), label]
               for position, (name, kind, label) in enumerate(entries, 1)]
    return select(tag, mode, columns, None)


def compound(tag, mode, left, right):
    return {**base(tag, mode), "compound": {"operator": "UNION", "all": True, "left": left, "right": right}}


def scope(tag, mode, definitions, body):
    return {**base(tag, mode), **with_scope(definitions, body)}


def markers(node):
    if "with" in node:
        for _, definition in node["with"]["definitions"]:
            yield from markers(definition)
        yield from markers(node["with"]["body"])
    elif "read" in node:
        return
    elif "local" in node:
        yield from markers(node["local"]["definition"])
    elif "compound" in node:
        for arm in ("left", "right"):
            yield from markers(node["compound"][arm])
    else:
        for operand, _ in node["columns"]:
            require(operand[0] in {"parameter", "text"}, "mysql-named-parameter-unregistered-field", operand)
            if operand[0] == "parameter":
                yield operand


def vector(node, inputs):
    anonymous = iter(inputs["anonymous"])
    result = []
    used = set()
    for operand in markers(node):
        name = key(operand)
        if name is None:
            try:
                result.append(next(anonymous))
            except StopIteration as failure:
                raise Failure("mysql-named-parameter-missing-anonymous-value", operand) from failure
        else:
            require(name in inputs["named"], "mysql-named-parameter-missing-name", operand)
            used.add(name)
            result.append(inputs["named"][name])
    same([], list(anonymous))
    same(sorted(inputs["named"]), sorted(used))
    return result


def bindings(named, *anonymous):
    return {"named": named, "anonymous": list(anonymous)}


def with_inputs(graph, values):
    return {**graph, "bindingInputs": values}


def finite(tag, mode, name, kind, pattern):
    other = UNSIGNED if kind == SIGNED else SIGNED
    columns = [(name, kind, "first_value")]
    values = bindings({name: -23})
    if pattern == "reused-name":
        columns.append((name, other, "reused_value"))
    elif pattern == "named-with-anonymous":
        columns = [(None, other, "anonymous_first"), *columns, (None, kind, "anonymous_last")]
        values = bindings({name: -23}, 1303, -17)
    return with_inputs(scan(tag, mode, columns), values)


def grid(spec):
    axes = spec["finite"]
    return product(*(axes[name] for name in axes["order"]))


def finite_ids(spec):
    axes = spec["finite"]
    recovered = {(mode, name, kind, pattern) for mode in axes["mode"] for name in axes["names"]
                 for kind in axes["target"] for pattern in axes["pattern"]}
    same(axes["identitiesPerProfile"], len(recovered))
    return recovered


def finite_id(mode, value):
    occurrences = list(markers(value))
    patterns = {
        ("named",): ("one", 0),
        ("named", "named"): ("reused-name", 0),
        ("anonymous", "named", "anonymous"): ("named-with-anonymous", 1),
    }
    pattern, position = patterns[tuple("anonymous" if key(operand) is None else "named" for operand in occurrences)]
    named = occurrences[position]
    return mode, key(named), cast(named), pattern


def seeded(tag, spec, seed):
    rng = Random(seed)
    modes = spec["finite"]["mode"]
    boundary_values = (None, 0, 17, -1, -2**63, MAXIMUM)
    for index in range(spec["exploration"]["casesPerSeed"]):
        mode = modes[index % len(modes)]
        branch = (index // len(modes)) % 4
        available = [*spec["finite"]["names"], f"seed_{rng.randrange(77)}"]
        width = 2 if branch == 1 else rng.randint(2, 6)
        names = [rng.choice([None, *available]) for _ in range(width)]
        kinds = [rng.choice((SIGNED, UNSIGNED)) for _ in names]
        entries = [(name, kind, f"binding_{slot}") for slot, (name, kind) in enumerate(zip(names, kinds))]
        values = bindings({name: rng.choice(boundary_values) for name in sorted({name for name in names if name is not None})},
                          *(rng.choice(boundary_values) for name in names if name is None))
        if branch == 0:
            graph = scan(tag, mode, entries)
        elif branch == 1:
            head, tail = entries[:1], entries[1:]
            graph = compound(tag, mode, scan(tag, mode, head), scan(tag, mode, tail))
        elif branch == 2 or tag in LEGACY:
            body = scan(tag, mode, entries)
            slots = rng.sample(range(len(entries)), len(entries))
            graph = local(body, [[slot, f"result_{position}"] for position, slot in enumerate(slots)])
        else:
            first = scan(tag, mode, entries[:1])
            second = scan(tag, mode, entries[1:])
            consumer = read(1, [[slot, f"result_{slot}"] for slot in range(len(entries) - 1)])
            graph = scope(tag, mode, [first, second], consumer)
        yield with_inputs(graph, values)


def parameter_kind(operand):
    return "uint64" if cast(operand) == UNSIGNED else "int64"


def evaluate(node, values, tag, visible=(), *, local_definition=False):
    if "with" in node:
        definitions = []
        for _, member in node["with"]["definitions"]:
            definitions.append(evaluate(member, values, tag, definitions))
        return evaluate(node["with"]["body"], values, tag, definitions)
    if "read" in node:
        definition, columns = node["read"]
        fields, rows = visible[definition]
        return pick(fields, rows, columns)
    if "local" in node:
        local_node = node["local"]
        fields, rows = evaluate(local_node["definition"], values, tag, local_definition=True)
        return pick(fields, rows, local_node["columns"])
    if "compound" in node:
        relation = node["compound"]
        left, right = relation["left"], relation["right"]
        kinds = [parameter_kind(arm["columns"][0][0]) for arm in (left, right)]
        left_name = left["columns"][0][1]
        kind = kinds[0] if tag in LEGACY or kinds[0] == kinds[1] else "decimal-integer"
        rows = [[wire(mathematical(next(values), cast(arm["columns"][0][0])), kind)] for arm in (left, right)]
        return [[left_name, "NEWDECIMAL" if kind == "decimal-integer" else "LONGLONG"]], rows
    fields, row = [], []
    for operand, name in node["columns"]:
        if operand[0] == "parameter":
            binding = next(values)
            legacy_null = local_definition and tag in LEGACY and binding is None
            fields.append([name, "LONG" if legacy_null else "LONGLONG"])
            row.append(wire(mathematical(binding, cast(operand)), parameter_kind(operand)))
        elif operand[0] == "text":
            fields.append([name, "VAR_STRING"])
            row.append(operand[1])
        else:
            raise Failure("mysql-named-parameter-unregistered-field", operand)
    return fields, [row]


def pick(fields, rows, columns):
    return ([[name, fields[slot][1]] for slot, name in columns],
            [[row[slot] for slot, _ in columns] for row in rows])
