"""Compare PostgreSQL text assignment and simultaneous old-row reads."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main, observer_request
from run_server_postgres_text_returning import (
    OBSERVER, analysis_request, check_preparation, expected, graph, int4, mutation, observation,
    prepare, reference, selected,
)
from run_server_postgres_typed_columns import DIAGNOSTICS
from server_join_cases import slot
from sqlite_domain import RenderFault

LABEL = "postgres-text-update"
CONTRACT = "e94efc62c3ba1ace6febcc09d4c77f8eed8b753e23327f8ef2bba2bd545bb0b6"
TEXT = ("", "it's different", "back\\slash", "line one\nline two", "多言語 α", "  ")


def specifications():
    path, profile = TOOL / "postgres-text-update-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-text-update-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def update(tag, writes, *, where=None, returned=(("label", "label"), ("note", "note"), ("id", "id"))):
    item = mutation(tag, "update", returned, where=where)
    item["assignments"] = [[reference(target), operand] for target, operand in writes]
    return item


def other(target):
    return {"label": "note", "note": "label"}[target]


def text(value):
    return ["text", value]


def finite(tag, form, target, filter_):
    opposite = other(target)
    operand = int4(int(form.removeprefix("int4:"))) if form.startswith("int4:") else {
        "literal": text(""), "read": reference(opposite), "null": ["null"], "swap": reference(opposite),
        "int4-read": reference("id"),
    }[form]
    writes = [(target, operand)]
    if form == "swap":
        writes.append((opposite, reference(target)))
    return update(tag, writes, where=None if filter_ == "all" else selected(target, True))


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    points = set(product(b["form"], b["target"], b["filter"]))
    require(len(points) == b["identities"], "postgres-text-update-finite-size", points)
    return points


def finite_id(_, reply, _item):
    _, _, _, op, (where, assignments), _ = graph(reply)
    require(op == "update", "postgres-text-update-wrong-role", op)
    destination, value = assignments[0]
    form = "swap" if len(assignments) == 2 else {"text": "literal", "text-slot": "read", "null": "null",
                                                "integer": f"int4:{value[-1]}", "slot": "int4-read"}[value[0]]
    if where is not None:
        require(where == ("null-test", True, ("text-slot", destination)),
                "postgres-text-update-finite-filter", where)
    return form, destination, "all" if where is None else "nonnull"


def positives(tag):
    table_name = "public.ir_text_changes"
    yield (f"UPDATE {table_name} SET label = 'it''s different' RETURNING label, note, id",
           update(tag, [("label", text("it's different"))]))
    yield (f"UPDATE {table_name} AS r SET note = r.label WHERE r.label IS NOT NULL RETURNING label, note, id",
           update(tag, [("note", reference("label"))], where=selected("label", True)))
    yield (f"UPDATE {table_name} SET label = note, note = label RETURNING label, note, id",
           update(tag, [("label", reference("note")), ("note", reference("label"))]))
    yield (f"UPDATE {table_name} SET label = NULL, id = 9 WHERE note IS NULL RETURNING label, note, id",
           update(tag, [("label", ["null"]), ("id", int4(9))], where=selected("note")))
    payload = "東京\nline"
    yield (f"UPDATE {table_name} SET label = CAST('{payload}' AS pg_catalog.text) RETURNING label, note, id",
           update(tag, [("label", text(payload))]))
    yield (f"UPDATE {table_name} SET label = -2147483648 RETURNING label, note, id",
           finite(tag, "int4:-2147483648", "label", "all"))
    yield (f"UPDATE {table_name} AS r SET label = r.id, id = 9 RETURNING label, note, id",
           update(tag, [("label", reference("id")), ("id", int4(9))]))


def negatives(tag):
    mismatch = "An UPDATE assignment value must match its target native kind."
    yield update(tag, [("label", slot(1))]), "An UPDATE read slot must belong to the target."
    yield update(tag, [("id", text("not int4"))]), mismatch
    wrong_write = update(tag, [("label", text("value"))])
    wrong_write["assignments"][0][0] = ["text-slot", 0, 0]
    yield wrong_write, "An UPDATE write slot must belong to the target."
    yield update(tag, [("label", ["text-slot", 0, 0])]), "An UPDATE read slot must belong to the target."
    yield update(tag, [("label", ["text-slot", 2, 1])]), "An UPDATE read slot must belong to occurrence zero."
    yield update(tag, [("label", text("first")), ("label", text("second"))]), "A PostgreSQL UPDATE cannot repeat a target slot."


def exploratory(tag, seed, count):
    rng = random.Random(seed)
    conversions = random.Random(seed + 1)
    for index in range(count):
        target = rng.choice(("label", "note"))
        value = rng.choice([reference(other(target)), ["null"], *(text(v) for v in TEXT)])
        if index % 6 == 0:
            value = reference("id") if index % 12 == 0 else int4(conversions.randrange(-2147483648, 2147483648))
        assignments = [(target, value)]
        if rng.getrandbits(1):
            assignments.append((other(target), rng.choice((reference(target), text(rng.choice(TEXT))))))
        if rng.getrandbits(1):
            assignments.append(("id", int4(rng.randint(-9, 9))))
        rng.shuffle(assignments)
        predicate = rng.choice((None, ["boolean", False], selected(target, True), selected(other(target))))
        fields = [(rng.choice(("label", "note", "id")), rng.choice(("label", '値 " out', "same")))
                  for _ in range(rng.randint(1, 5))]
        yield update(tag, assignments, where=predicate, returned=fields)


def requests(_, tag, spec):
    b = spec["finite"]
    for index, point in enumerate(product(b["form"], b["target"], b["filter"])):
        item = finite(tag, *point)
        yield f"finite/{index}", item, item, None
    for index, (sql, item) in enumerate(positives(tag)):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(exploratory(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-text-update-positive-rejected", reply)
    require(graph(item) == graph(reply), "postgres-text-update-graph-differs", {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "mutation", "postgres-text-update-native-rejection", {"sql": sql, "actual": actual})
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "mutation", "postgres-text-update-fault-native")


def sample_forms(forms, reply, *_):
    _, _, _, _, (where, assignments), _ = graph(reply)
    destinations = {dest for dest, _ in assignments}
    if "id" in destinations:
        forms["mixed-signed-write"] += 1
    for position, (destination, term) in enumerate(assignments):
        forms["destination/" + destination] += 1
        forms["operand/" + term[0]] += 1
        if term[0] == "text":
            forms["literal/" + json.dumps(term[1], ensure_ascii=False)] += 1
        if term[0] == "text-slot" and term[1] in destinations:
            forms["read-write-overlap"] += 1
        if destination != "id" and term[0] in {"integer", "slot"}:
            forms["assignment/int4-to-text/" + term[0]] += 1
        if position == 0 and destination != "id":
            forms["text-first/" + destination] += 1
    forms[f"filter/{where is not None}"] += 1
    forms[f"nonempty/{bool(expected(reply)['count'])}"] += 1


def required_forms(*_):
    return {"destination/label", "destination/note", "operand/text", "operand/null", "operand/text-slot",
            "mixed-signed-write", "read-write-overlap", "text-first/label", "text-first/note",
            "filter/True", "filter/False", "nonempty/True", "nonempty/False", "assignment/int4-to-text/integer", "assignment/int4-to-text/slot"} | {
        "literal/" + json.dumps(v, ensure_ascii=False) for v in TEXT
    }


def controls():
    item = finite("pg-17.2", "swap", "label", "all")
    prediction = expected(item)
    check_observation(item, prediction, "known simultaneous text swap")
    broken = {**prediction, "after": prediction["before"]}
    try:
        check_observation(item, broken, "return rows without a physical swap")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "postgres-text-update-control-signal", failure.detail)
    else:
        raise Failure("postgres-text-update-comparator-accepted")
    return {"healthy": prediction, "wrongPhysicalEffect": broken}


def faults(_, tag):
    sql = "sql-semantics-postgres/src/Ir/SelectSql.php"
    update_path = "sql-semantics-postgres/src/Ir/Mutation/Update.php"
    emit = "$parts[] = $column . ' = ' . $sql->expression($assignment->value, $this->target);"
    return [
        RenderFault("drop-second-text-assignment", update_path, emit, f"if ($parts === []) {{ {emit} }}",
                    finite(tag, "swap", "label", "all"), "meaning-mismatch"),
        RenderFault("read-wrong-old-text-field", sql, "$input->columns[$expression->slot]->quoted()",
                    "$input->columns[$expression instanceof TextColumnReference ? 1 : $expression->slot]->quoted()",
                    finite(tag, "read", "label", "all"), "meaning-mismatch"),
        RenderFault("treat-empty-text-as-null", sql, "$expression instanceof TextLiteral => $expression->toString(),",
                    "$expression instanceof TextLiteral => $expression->value === '' ? 'NULL' : $expression->toString(),",
                    finite(tag, "literal", "label", "all"), "meaning-mismatch"),
        RenderFault("substitute-int4-update-before-text-assignment", sql,
                    "$expression instanceof IntegerLiteral => 'CAST(' . $expression->decimal . ' AS pg_catalog.int4)',",
                    "$expression instanceof IntegerLiteral => 'CAST(0 AS pg_catalog.int4)',",
                    finite(tag, "int4:-27", "label", "all"), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
