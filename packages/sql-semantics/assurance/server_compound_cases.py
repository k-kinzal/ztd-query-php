"""Independently interpret typed output roles and weighted rows of native compounds."""

from collections import Counter
from copy import deepcopy
from itertools import product
import json
from random import Random

from server_cases import SCHEMAS, observation
from server_delete_cases import literal
from server_join_cases import NAMES_BY_TABLE, rows, slot, table, truth, value

OPERATORS = ("UNION", "INTERSECT", "EXCEPT")


def legacy(tag):
    return tag.startswith(("mysql-5.6.", "mysql-5.7."))


def leaf(engine, tag, columns, source=None, where=None):
    return {"dialect": engine, "release": tag, "source": source, "columns": columns,
            "where": where, "distinct": False, "limit": None, "orderBy": []}


def case(engine, tag, left, operator, all_rows, right):
    return {"dialect": engine, "release": tag, "compound": {"left": left, "operator": operator, "all": all_rows, "right": right}}


def field_list(value, width, prefix):
    return [[value[index], f"{prefix}{index}"] for index in range(width)]


def pair(engine, tag, role, width):
    a = leaf(engine, tag, field_list([literal(1), literal(2)], width, "a"))
    b = leaf(engine, tag, field_list([literal(1), literal(2)], width, "b"))
    if role == 1:
        a = leaf(engine, tag, field_list([slot(0), slot(1)], width, "a"), table(engine, "accounts"))
        b = leaf(engine, tag, field_list([slot(0), slot(1)], width, "b"), table(engine, "independent"))
    elif role == 2:
        b["where"] = ["boolean", False]
    elif role == 3:
        a["columns"][0][0] = ["null"]
    return a, b


def without_profiles(query):
    if "compound" not in query:
        return {name: value for name, value in query.items() if name not in ("dialect", "release")}
    branch = query["compound"]
    return {"compound": {**branch, "left": without_profiles(branch["left"]), "right": without_profiles(branch["right"])}}


def finite_cases(engine, tag):
    for role, operator, all_rows, width in product(range(4), OPERATORS[:1] if legacy(tag) else OPERATORS, (False, True), (1, 2)):
        left, right = pair(engine, tag, role, width)
        yield case(engine, tag, left, operator, all_rows, right)


def finite_id(engine, response, _):
    branch = response["compound"]
    left, right = branch["left"], branch["right"]
    if left["source"] is not None:
        role = 1
    elif right["where"] is not None:
        role = 2
    elif left["columns"][0][0] == ["null"]:
        role = 3
    else:
        role = 0
    return role, branch["operator"], branch["all"], len(left["columns"])


def finite_ids(engine, specification, tag):
    finite = specification["finite"]
    return set(product(range(len(finite["pairs"])), finite["operators"]["mysql-legacy" if legacy(tag) else engine], (False, True), finite["widths"]))


def seeded(engine, tag, seed, count):
    rng = Random(seed)
    allowed = OPERATORS[:1] if legacy(tag) else OPERATORS

    def query(width, depth, scope):
        if depth == 0 or rng.randrange(4) == 0:
            role = rng.choice((None, "accounts", "independent", "empty"))
            source = table(engine, role) if role is not None else None
            atoms = [literal(rng.randrange(-3, 4))]
            if source is not None:
                atoms.extend((slot(0), slot(1), ["operation", "+", slot(0), literal(1)]))
            fields = [[rng.choice(atoms), scope + str(i)] for i in range(width)]
            return {**leaf(engine, tag, fields, source, rng.choice((None, None, ["boolean", False]))),
                    "distinct": bool(rng.getrandbits(1))}
        left = query(width, depth - 1, scope + "l")
        right = query(width, depth - 1 if not legacy(tag) else 0, scope + "r")
        return case(engine, tag, left, rng.choice(allowed), bool(rng.getrandbits(1)), right)

    for _ in range(count):
        width, depth = rng.randint(1, 3), rng.randint(1, 3)
        yield case(engine, tag, query(width, depth - 1, "l"), rng.choice(allowed), bool(rng.getrandbits(1)),
                   query(width, depth - 1 if not legacy(tag) else 0, "r"))


def analyzed(engine, tag):
    a, b = pair(engine, tag, 0, 1)
    c = leaf(engine, tag, [[literal(3), "c0"]])
    examples = [
        ("SELECT 1 AS a0 UNION ALL SELECT 1 AS b0", case(engine, tag, a, "UNION", True, b)),
        ("SELECT 1 AS a0 UNION ALL SELECT 1 AS b0 UNION DISTINCT SELECT 3 AS c0",
         case(engine, tag, case(engine, tag, a, "UNION", True, b), "UNION", False, c)),
        ("SELECT NULL AS a0 UNION SELECT 1 AS b0", case(engine, tag, leaf(engine, tag, [[["null"], "a0"]]), "UNION", False, b)),
    ]
    null_left, null_right = (leaf(engine, tag, [[["null"], label]]) for label in ("a0", "b0"))
    examples.extend((
        ("SELECT NULL AS a0 UNION ALL SELECT NULL AS b0", case(engine, tag, null_left, "UNION", True, null_right)),
        ("SELECT NULL AS a0 UNION SELECT NULL AS b0", case(engine, tag, null_left, "UNION", False, null_right)),
    ))
    bool_left, bool_right = (leaf(engine, tag, [[["boolean", value], label]]) for label, value in (("a0", True), ("b0", False)))
    examples.append(("SELECT TRUE AS a0 UNION ALL SELECT FALSE AS b0", case(engine, tag, bool_left, "UNION", True, bool_right)))
    if not legacy(tag):
        examples.append(("SELECT 1 AS a0 UNION ALL (SELECT 1 AS b0 EXCEPT ALL SELECT 3 AS c0)",
                         case(engine, tag, a, "UNION", True, case(engine, tag, b, "EXCEPT", True, c))))
    schema = SCHEMAS[engine]
    name = "Id" if engine == "mysql" else "id"
    first, second = (schema + "." + NAMES_BY_TABLE[role] for role in ("accounts", "independent"))
    left, right = pair(engine, tag, 1, 1)
    left["source"]["columns"] = right["source"]["columns"] = [name]
    examples.append((f"SELECT {name} AS a0 FROM {first} UNION ALL SELECT {name} AS b0 FROM {second}",
                     case(engine, tag, left, "UNION", True, right)))
    declarations = [f"CREATE TABLE {table} ({name} INTEGER, delta INTEGER)" for table in (first, second)]
    for sql, item in examples:
        yield {"dialect": engine, "release": tag, "declarations": declarations, "sql": sql}, item


def negatives(engine, tag):
    a, b = pair(engine, tag, 0, 1)
    wide, _ = pair(engine, tag, 0, 2)
    yield case(engine, tag, a, "UNION", False, wide), "same width"
    other = ("pg-16.6" if tag == "pg-17.2" else "pg-17.2") if engine == "postgres" else (
        "mysql-8.4.7" if tag == "mysql-9.1.0" else "mysql-9.1.0")
    yield case(engine, tag, a, "UNION", False, {**b, "release": other}), "same language profile"
    yield case(engine, tag, a, "UNION", False, {**b, "orderBy": [[1, False, engine == "mysql"]]}), "without ordering"
    chain = a
    for _ in range(17):
        chain = case(engine, tag, chain, "UNION", False, b)
    yield chain, "at most 16 nested operations"
    if not legacy(tag):
        repeated = a
        for _ in range(7):
            repeated = case(engine, tag, repeated, "UNION", False, deepcopy(repeated))
        yield repeated, "at most 64 query arms"
    if engine == "postgres":
        yield case(engine, tag, a, "UNION", False, leaf(engine, tag, [[["boolean", True], "b0"]])), "output types are incompatible"
    if legacy(tag):
        yield case(engine, tag, a, "INTERSECT", False, b), "left-associated UNION"
        yield case(engine, tag, a, "UNION", False, case(engine, tag, a, "UNION", False, b)), "left-associated UNION"


def requests(engine, tag, specification):
    for i, item in enumerate(finite_cases(engine, tag)):
        yield f"finite/{i}", item, item, None
    exploration = specification["exploration"]
    for seed in exploration["seeds"]:
        for i, item in enumerate(seeded(engine, tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{i}", item, item, None
    for i, (request, item) in enumerate(analyzed(engine, tag)):
        yield f"analyzed/{i}", request, item, None
    for i, (item, signal) in enumerate(negatives(engine, tag)):
        yield f"negative/{i}", item, item, signal


def column_type(expression, engine):
    if expression[0] == "null":
        return "unknown"
    if engine == "postgres":
        return "bool" if expression[0] == "boolean" else "int4"
    return "LONG" if expression[0] == "slot" else "LONGLONG"


def combined_type(left, right, engine):
    if engine == "postgres":
        if left == right == "unknown":
            return "text"
        if left == "unknown":
            return right
        assert right == "unknown" or right == left
        return left
    present = {left, right}
    for kind in ("LONGLONG", "LONG"):
        if kind in present:
            return kind
    assert present <= {"unknown", "null-only"}
    return "null-only"


def evaluate(query, engine):
    if "compound" not in query:
        fields = query["columns"]
        names, types = [name for _, name in fields], [column_type(expr, engine) for expr, _ in fields]
        bag = Counter(json.dumps([value(expr, row, engine) for expr, _ in fields]) for row in rows(query["source"], engine)
                      if truth(query["where"], row, engine))
        return names, types, Counter(bag.keys()) if query["distinct"] else bag
    branch = query["compound"]
    labels, a_types, a = evaluate(branch["left"], engine)
    _, b_types, b = evaluate(branch["right"], engine)
    types = [combined_type(lhs, rhs, engine) for lhs, rhs in zip(a_types, b_types)]
    if not branch["all"]:
        a, b = Counter(a.keys()), Counter(b.keys())
    if branch["operator"] == "UNION":
        bag = a + b if branch["all"] else a | b
    elif branch["operator"] == "INTERSECT":
        bag = a & b
    else:
        assert branch["operator"] == "EXCEPT"
        bag = a - b
    return labels, types, bag


def all_unions(query):
    if "compound" not in query:
        return True
    branch = query["compound"]
    return branch["operator"] == "UNION" and branch["all"] and all(all_unions(branch[side]) for side in ("left", "right"))


def expected(item, null_only_tags):
    labels, types, bag = evaluate(item, item["dialect"])
    tag = None
    if "null-only" in types:
        profile = null_only_tags[item["release"]]
        tag = profile["unionsAll" if all_unions(item) else "other"]
    return observation({"status": "rows", "fields": [[label, tag if kind == "null-only" else kind] for label, kind in zip(labels, types)],
                        "rows": [json.loads(row) for row in bag.elements()]})
