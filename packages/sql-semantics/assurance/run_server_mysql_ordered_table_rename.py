"""Compare ordered native MySQL table relocations with an independent namespace model."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_mysql_drop_view import specifications as profiles
from sqlite_domain import RenderFault

LABEL = "mysql-ordered-table-rename"
OBSERVER = "mysql_ordered_table_rename_observer.php"
CONTRACT_NAME = "mysql-ordered-table-rename-v2.json"
CONTRACT = "8fd94c11049645a17e8775960a7da377d610ece5deca2bbf4b0953ed27bc1954"
COMPLETE_SEEDS = True
SCHEMA, ARCHIVE = "ir_fixture", "ir_rm_archive"
A, B, TEMP, FINAL = "ir_rm_a", "ir_rm_b", "ir_rm_temp", "ir_rm_final"
DIAGNOSTIC = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\InvalidConstruction"
SEMANTIC = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\SemanticException"
AXES = ("fixture", "form", "placement")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-ordered-rename-contract-mismatch")
    return json.loads(path.read_text()), profiles()[1]


def identity(name, schema=SCHEMA):
    return [schema, name]


def move(source, destination, origin=None):
    return {"from": source, "to": destination, "origin": source if origin is None else origin}


def rename(tag, moves, fixture="two"):
    return {"dialect": "mysql", "release": tag, "fixture": fixture, "tableRename": moves}


def steps(form, placement, temp=TEMP, final=FINAL):
    first, second = (identity(A), identity(B)) if placement == "ordinary" else (identity(B), identity(A))
    if form == "cross-schema":
        intermediate = identity(temp, ARCHIVE)
        return [move(first, intermediate), move(intermediate, identity(final), first)]
    intermediate = identity(temp)
    if form == "single":
        return [move(first, intermediate)]
    if form == "chain":
        return [move(first, intermediate), move(intermediate, identity(final), first)]
    if form == "swap":
        return [move(first, intermediate), move(second, first), move(intermediate, second, first)]
    require(form == "independent", "mysql-rename-unknown-form", form)
    return [move(first, intermediate), move(second, identity(final))]


def finite(tag, fixture, form, placement):
    return rename(tag, steps(form, placement), fixture)


def finite_ids(_, spec, _tag):
    domain = spec["finite"]
    expected = set(product(*(domain[axis] for axis in AXES)))
    require(len(expected) == domain["identities"], "mysql-rename-grid-size")
    return expected


def observed_form(moves):
    if any(m[role][0] != SCHEMA for m in moves for role in ("from", "to")):
        return "cross-schema"
    if len(moves) == 1:
        return "single"
    if len(moves) == 3 and moves[1]["to"] == moves[0]["from"] and moves[2]["to"] == moves[1]["from"]:
        return "swap"
    if moves[0]["to"] == moves[1]["from"]:
        return "chain"
    return "independent"


def finite_id(_, reply, item):
    moves = reply["tableRename"]
    require(moves[0]["from"] in [identity(A), identity(B)], "mysql-rename-finite-source", moves)
    return item["fixture"], observed_form(moves), "ordinary" if moves[0]["from"] == identity(A) else "reversed"


def declaration(name, schema=SCHEMA):
    return f"CREATE TABLE {schema}.{name} (id INT, label VARCHAR(8))"


def analysis_request(tag, sql, current=True, declarations=None):
    return {"dialect": "mysql", "release": tag, "sql": sql,
            "declarations": [declaration(A), declaration(B), declaration(A, ARCHIVE)] if declarations is None else declarations,
            **({"currentDatabase": SCHEMA} if current else {})}


def analyzed(tag):
    yield f"RENAME TABLE {SCHEMA}.{A} TO {SCHEMA}.{TEMP}", finite(tag, "two", "single", "ordinary")
    yield f"RENAME TABLE {B} TO {TEMP}", finite(tag, "two", "single", "reversed")
    yield f"RENAME TABLE {A} TO {TEMP}, {TEMP} TO {FINAL}", finite(tag, "two", "chain", "ordinary")
    yield f"RENAME TABLE {A} TO {TEMP}, {B} TO {A}, {TEMP} TO {B}", finite(tag, "two", "swap", "ordinary")
    yield f"RENAME TABLE {SCHEMA}.{A} TO {ARCHIVE}.{TEMP}", rename(tag, [move(identity(A), identity(TEMP, ARCHIVE))])
    yield f"RENAME TABLE {A} TO {ARCHIVE}.{TEMP}, {ARCHIVE}.{TEMP} TO {SCHEMA}.{FINAL}", finite(tag, "two", "cross-schema", "ordinary")
    yield f"RENAME TABLE {A} TO {TEMP}, {B} TO {FINAL}", finite(tag, "two", "independent", "ordinary")
    yield f"RENAME TABLE {A} TO `ir_rm_long``name`", rename(tag, [move(identity(A), identity("ir_rm_long`name"))])


def negatives(tag):
    a, b, temp, destination = identity(A), identity(B), identity(TEMP), identity(FINAL)
    yield rename(tag, []), DIAGNOSTIC, "RENAME TABLE needs a nonempty ordered sequence."
    yield rename(tag, [move(a, a)]), DIAGNOSTIC, "A table rename requires distinct current and destination identities."
    yield rename(tag, [move(a, temp, b)]), DIAGNOSTIC, "A rename move must retain its originating table identity."
    yield rename(tag, [move(a, temp), move(a, destination)]), DIAGNOSTIC, "A vacated source must have been recreated by an earlier move."
    yield rename(tag, [move(a, temp), move(b, temp)]), DIAGNOSTIC, "A rename destination cannot replace a table moved there by an earlier step."
    yield analysis_request(tag, f"RENAME TABLE {A} TO {TEMP}", declarations=[declaration(B)]), SEMANTIC, "does not exist"
    yield analysis_request(tag, f"RENAME TABLE {A} TO {TEMP}", declarations=[f"CREATE VIEW {SCHEMA}.{A} AS SELECT 1 AS id"]), DIAGNOSTIC, \
        "RENAME TABLE requires a resolved persistent base-table origin."
    yield analysis_request(tag, f"RENAME TABLE {SCHEMA}.{A} TO {TEMP}", current=False), DIAGNOSTIC, \
        "RENAME TABLE needs absolute current, origin and destination names."
    yield analysis_request(tag, f"RENAME TABLE {A} TO {TEMP}", declarations=[f"CREATE TEMPORARY TABLE {SCHEMA}.{A}(id int)"]), DIAGNOSTIC, \
        "RENAME TABLE requires a resolved persistent base-table origin."


def extended_chain(first, length, rng):
    moves = []
    current = first
    for position in range(length):
        target = identity(f"ir_rm_change{position}", rng.choice((SCHEMA, ARCHIVE)))
        moves.append(move(current, target, first))
        current = target
    return moves


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        fixture = rng.choice(("two", "absent-second", "blocked"))
        placement = rng.choice(("ordinary", "reversed"))
        form = ("single", "chain", "swap", "cross-schema", "independent", "long")[index % 6]
        if form == "long":
            moves = extended_chain(identity(A if placement == "ordinary" else B), 4 + rng.randrange(3), rng)
        else:
            destination = rng.choice((FINAL, "ir_rm_caps A", "ir_rm_a`z", "ir_rm_" + "x" * 58))
            moves = steps(form, placement, TEMP, destination)
        yield rename(tag, moves, fixture)


def requests(_, tag, spec):
    for index, dimensions in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    authored = list(analyzed(tag))
    require(len(authored) == len(spec["analysis"]), "mysql-rename-analysis-count")
    for index, (sql, item) in enumerate(authored):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    invalid = list(negatives(tag))
    require(len(invalid) == len(spec["negative"]), "mysql-rename-negative-count")
    for index, (input_, exception, message) in enumerate(invalid):
        yield f"negative/{index}", input_, None, {"exception": exception, "message": message}


def coordinate(name):
    return ".".join(name)


def table(rows, length, nullable):
    return {"engine": "InnoDB", "rows": sorted(rows, key=json.dumps), "fields": [
        ["id", "int", "YES" if nullable else "NO", None, None],
        ["label", "varchar", "YES", length, "utf8mb4_bin"],
    ]}


def starting(fixture):
    tables = {coordinate(identity(A)): table([[1, "a"], [None, "empty"], [1, "a"]], 8, True),
              coordinate(identity(A, ARCHIVE)): table([[91, "decoy"]], 16, False)}
    if fixture != "absent-second":
        tables[coordinate(identity(B))] = table([[7, "b"]], 12, False)
    if fixture == "blocked":
        tables[coordinate(identity(TEMP))] = table([[50, "occupied"]], 10, False)
    return tables


def prediction(item):
    before = starting(item["fixture"])
    tables = dict(before)
    effect = ["executed"]
    for move in item["tableRename"]:
        source, destination = coordinate(move["from"]), coordinate(move["to"])
        if source not in tables:
            missing = ("HY000", 1017) if item["release"] in ("mysql-5.6.51", "mysql-5.7.44") else ("42S02", 1146)
            effect = ["db-error", *missing]
            break
        if destination in tables:
            effect = ["db-error", "42S01", 1050]
            break
        tables[destination] = tables.pop(source)
    durable = {"tables": tables if effect[0] == "executed" else before, "marker": [0, 7]}
    return {"status": "ordered-mysql-rename", "namespace": SCHEMA, "before": {"tables": before, "marker": [0]},
            "effect": effect, "active": False, "after": durable, "afterRollback": durable}


def normalize(state):
    return {**state, "tables": {name: {**relation, "rows": sorted(relation["rows"], key=json.dumps)}
                               for name, relation in state["tables"].items()}}


def observation(raw):
    if raw["status"] != "ordered-mysql-rename":
        return raw
    return {**raw, **{key: normalize(raw[key]) for key in ("before", "after", "afterRollback")}}


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"] == item["release"] +
            "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native", "mysql-rename-positive-rejected", response)
    same(item["tableRename"], response["tableRename"])


def observer_request(item, sql):
    return {"sql": sql, "fixture": item["fixture"]}


def prepare(_, db):
    return {"initial": db.environment["initial"]}


def check_preparation(_, environment, preparation):
    same(environment["initial"], preparation["initial"])
    same({"tables": starting("two"), "marker": [0]}, normalize(environment["initial"]))


def check_observation(item, actual, sql):
    wanted = prediction(item)
    witness = {"sql": sql, "expected": wanted, "observed": actual}
    require(actual["status"] == "ordered-mysql-rename", "mysql-rename-observer-rejected", witness)
    try:
        same(wanted, actual)
    except Failure as error:
        raise Failure(error.signal, witness) from error


def sample_forms(forms, reply, _, item):
    moves = reply["tableRename"]
    forms[f"form/{observed_form(moves)}"] += 1
    forms[f"fixture/{item['fixture']}"] += 1
    forms[f"steps/{len(moves)}"] += 1
    forms[f"bound-through-move/{any(m['from'] != m['origin'] for m in moves)}"] += 1


def required_forms(*_):
    return {*(f"form/{form}" for form in ("single", "chain", "swap", "cross-schema", "independent")),
            *(f"fixture/{fixture}" for fixture in ("two", "absent-second", "blocked")),
            *(f"steps/{size}" for size in (1, 2, 3, 4, 5, 6)), "bound-through-move/True", "bound-through-move/False"}


def controls():
    item = finite("mysql-8.4.7", "two", "swap", "ordinary")
    check_observation(item, prediction(item), "correct ordered swap")
    reduced = rename(item["release"], item["tableRename"][:1])
    try:
        check_observation(item, prediction(reduced), "remaining moves discarded")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"wrong-rename-order-effect": {"input": item, **error.detail}}
    raise Failure("mysql-rename-comparator-missed-order")


def boundary(item, response):
    if rejects(response, item["boundary"]):
        return
    require(response["status"] == "constructed", "mysql-rename-mutant-response", response)
    raise Failure("mysql-rename-invalid-published", {"sql": response["sql"], "observed": response, "expected": item["boundary"]})


def fault_probe(item, response, db):
    if "boundary" in item:
        boundary(item, response)
    else:
        probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            boundary(rule.item, record["healthyResponse"])
            boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(prediction(rule.item), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("mysql-rename-missed-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-mysql/src/Ir/Schema/Alter"
    pair, statement = f"{root}/TableRename.php", f"{root}/RenameTable.php"
    single = finite(tag, "two", "single", "ordinary")
    chain = finite(tag, "two", "chain", "ordinary")
    invalid, exception, message = list(negatives(tag))[2]
    operands = "$this->from->quoted() . ' TO ' . $this->to->quoted()"
    return [
        RenderFault("mysql-rename-deceive-source-schema", pair, "$this->from->quoted()", "'`ir_rm_archive`.`ir_rm_a`'",
                    single, "meaning-mismatch"),
        RenderFault("mysql-rename-swap-move-operands", pair, operands, "$this->to->quoted() . ' TO ' . $this->from->quoted()",
                    single, "meaning-mismatch"),
        RenderFault("mysql-rename-drop-move", statement, "foreach ($this->moves as $move)", "foreach (array_slice($this->moves, 0, 1) as $move)",
                    chain, "meaning-mismatch"),
        RenderFault("mysql-rename-reverse-move-order", statement, "foreach ($this->moves as $move)", "foreach (array_reverse($this->moves) as $move)",
                    chain, "meaning-mismatch"),
        RenderFault("mysql-rename-substitute-destination", pair, "$this->to->quoted()", "'`ir_fixture`.`ir_rm_forged`'",
                    single, "meaning-mismatch"),
        RenderFault("mysql-rename-admit-wrong-origin", statement, "$origin->quoted() === $move->origin->quoted(),", "true,",
                    {"boundary": {"exception": exception, "message": message}}, "mysql-rename-invalid-published", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
