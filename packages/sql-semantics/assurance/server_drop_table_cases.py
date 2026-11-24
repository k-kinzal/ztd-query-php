"""Predict catalog and transaction effects of a native ordinary table removal."""

from itertools import product
from random import Random

from server_cases import SCHEMAS

ROWS = {"a": [11], "b": [23], "control": [-1]}
SEQUENCES = (("a",), ("b",), ("a", "b"), ("a", "missing"))


def case(engine, tag, names, if_exists=False, cascade=False, dependent=False):
    return {"dialect": engine, "release": tag, "drop": {"targets": [[SCHEMAS[engine], "ir_drop_" + name] for name in names],
            "ifExists": if_exists, **({"cascade": cascade} if engine == "postgres" else {})}, "dependent": dependent}


def finite_cases(engine, tag):
    cascades = (False, True) if engine == "postgres" else (False,)
    for names, optional, cascade, dependent in product(SEQUENCES, (False, True), cascades, (False, True)):
        yield case(engine, tag, names, optional, cascade, dependent)


def finite_id(_, response, item):
    value = response["drop"]
    sequence = tuple(target[1].removeprefix("ir_drop_") for target in value["targets"])
    return SEQUENCES.index(sequence), value["ifExists"], value.get("cascade", False), item["dependent"]


def finite_ids(engine, specification, _tag):
    finite = specification["finite"]
    return set(product(range(len(finite["targetSequences"])), (False, True),
                       (policy == "cascade" for policy in finite["dependencyPolicies"][engine]), (False, True)))


def seeded(engine, tag, seed, count):
    rng = Random(seed)
    names = (*ROWS, "missing")
    for _ in range(count):
        yield case(engine, tag, rng.sample(names, rng.randint(1, len(names))),
                   bool(rng.getrandbits(1)), bool(rng.getrandbits(1)), bool(rng.getrandbits(1)))


def analyzed(engine, tag):
    schema = SCHEMAS[engine]
    a, b, missing = (f"{schema}.ir_drop_{name}" for name in ("a", "b", "missing"))
    declarations = [f"CREATE TABLE {name} (value INTEGER)" for name in (a, b)]
    examples = [
        (f"DROP TABLE {a}", case(engine, tag, ["a"])),
        (f"DROP TABLE {b}, {a} CASCADE", case(engine, tag, ["b", "a"], cascade=True, dependent=True)),
        (f"DROP TABLE IF EXISTS {a}, {missing} RESTRICT", case(engine, tag, ["a", "missing"], if_exists=True)),
        (f"DROP TABLE IF EXISTS {missing}", case(engine, tag, ["missing"], if_exists=True)),
    ]
    for sql, item in examples:
        yield {"dialect": engine, "release": tag, "sql": sql, "declarations": declarations}, item


def negatives(engine, tag):
    yield case(engine, tag, []), "nonempty target list"
    yield case(engine, tag, ["a", "a"]), "cannot repeat a target"
    bad = case(engine, tag, ["a"])
    bad["drop"]["targets"][0][0] = "invalid/schema" if engine == "mysql" else "pg_temp"
    yield bad, "without path separators" if engine == "mysql" else "absolute schema"
    schema = SCHEMAS[engine]
    for sql, message in [
        (f"DROP TABLE IF EXISTS {schema}.ir_drop_view", "ordinary table" if engine == "mysql" else "is not a table"),
        ("DROP TABLE IF EXISTS unknown_schema.ir_drop_a", "known explicit schema"),
        (f"DROP TEMPORARY TABLE {schema}.ir_drop_a" if engine == "mysql" else f"DROP SEQUENCE {schema}.ir_drop_sequence",
         {"exception": "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\SemanticException", "message": "does not exist"}
         if engine == "mysql" else {"exception": "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\InvalidConstruction",
                                    "message": "An absent sequence selector requires IF EXISTS."}),
    ]:
        yield {"dialect": engine, "release": tag, "sql": sql, "declarations": [
            f"CREATE TABLE {schema}.ir_drop_a (value INTEGER)",
            f"CREATE VIEW {schema}.ir_drop_view AS SELECT 1 AS value",
        ]}, message


def requests(engine, tag, specification):
    for i, item in enumerate(finite_cases(engine, tag)):
        yield f"finite/{i}", item, item, None
    for seed in specification["exploration"]["seeds"]:
        for i, item in enumerate(seeded(engine, tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{i}", item, item, None
    for i, (request, item) in enumerate(analyzed(engine, tag)):
        yield f"analyzed/{i}", request, item, None
    for i, (item, signal) in enumerate(negatives(engine, tag)):
        yield f"negative/{i}", item, item, signal


def expected(item):
    engine = item["dialect"]
    value = item["drop"]
    names = [parts[1].removeprefix("ir_drop_") for parts in value["targets"]]
    dependent = item["dependent"]
    cascade = value.get("cascade", False)
    missing = "missing" in names and not value["ifExists"]
    needs_dependency = dependent and "a" in names and not cascade
    view = dependent
    rows = ROWS.copy()
    rows["marker"] = [0, 7]
    if engine == "postgres":
        if missing:
            status = "42P01"
        elif needs_dependency:
            status = "2BP01"
        else:
            status = "dropped"
            for name in names:
                rows.pop(name, None)
            view = dependent and not ("a" in names and cascade)
        after = None if status != "dropped" else {"rows": rows, "view": view}
        return {"status": status, "active": True, "effect": after, "restored": {"rows": {**ROWS, "marker": [0]}, "view": dependent}}
    status = "42S02" if missing else "dropped"
    legacy = item["release"].startswith(("mysql-5.6.", "mysql-5.7."))
    if not missing or legacy:
        for name in names:
            rows.pop(name, None)
    effect = {"rows": rows, "view": view}
    return {"status": status, "active": False, "effect": effect, "restored": effect}


def observation(raw):
    return raw
