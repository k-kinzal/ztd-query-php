"""Observe PostgreSQL native signed assignment widths and affected rows."""

import json
import random
import sys
from itertools import product

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_postgres_boolean_fields import DIAGNOSTIC, query
from run_server_postgres_int8_fields import specifications as reads
from server_join_cases import compare, slot
from server_truth_model import admits
from sqlite_domain import RenderFault

LABEL = "postgres-signed-write-widths"
CONTRACT = "5726305480ba26a37d4f3b59444c6e6447d0ca43eed8c4eb284292f4cbcf1e71"
OBSERVER = "postgres_signed_write_observer.php"
COMPLETE_SEEDS = True
TARGET, INPUT = "ir_signed_changes", "ir_signed_inputs"
T, S = f"public.{TARGET}", f"public.{INPUT}"
MIN, MAX = -(2**63), 2**63 - 1
KINDS = {"id": "int4", "wide": "int8", "note": "text", "enabled": "bool"}
HEADING = {TARGET: tuple(KINDS), INPUT: ("wide", "id")}
OID = {"int4": 23, "int8": 20, "text": 25, "bool": 16}
FIELDS = {"int4": "slot", "int8": "wide-slot", "text": "text-slot", "bool": "bool-slot"}
NAME = {"int4": "id", "int8": "wide", "text": "note"}
DEFAULTS = dict(zip(KINDS, (11, 2147483648, "kept", True)))
INITIAL = [(1, -7, "one", True), (1, -7, "one", True), (2, MIN, "min", False), (3, MAX, "max", None), (None, None, None, True)]
INPUT_ROWS = [(23, 1), (23, 1), (MIN, 2), (MAX, 3), (None, None)]
DDL = [f"CREATE TABLE {T} (id INTEGER DEFAULT 11, wide BIGINT DEFAULT 2147483648, note TEXT DEFAULT 'kept', enabled BOOLEAN DEFAULT TRUE)",
       f"CREATE TABLE {S} (wide BIGINT, id INTEGER)"]
ATOMS = {"int4": ["integer", "7"], "int8-local": ["wide-integer", "-9"], "int8-min": ["wide-integer", str(MIN)],
         "int8-max": ["wide-integer", str(MAX)], "null": None}
RESULT = [("wide", "stored"), ("id", "signed"), ("note", "comment"), ("enabled", "flag")]
LAYOUTS = {
    "int8, int4": [("wide", "first_value"), ("id", "second_value")],
    "int4, int8": [("id", "first_value"), ("wide", "second_value")],
    "duplicate int8": [("wide", "first_value"), ("wide", "second_value")],
}
NULL_ERROR = "A DISTINCT insert projection cannot assign an untyped NULL to a non-text column."


def specifications():
    path = TOOL / "postgres-signed-write-widths-v1.json"
    require(digest(path) == CONTRACT, "postgres-signed-write-contract-mismatch")
    return json.loads(path.read_text()), reads()[1]


def table(name):
    return {"schema": "public", "table": name, "only": False,
            "columns": list(HEADING[name]), "columnKinds": [KINDS[key] for key in HEADING[name]]}


def ref(name, table_name=TARGET):
    position = HEADING[table_name].index(name)
    role = FIELDS[KINDS[name]]
    return slot(position) if role == "slot" else [role, position, 0]


def atom_kind(operand):
    if type(operand) is str:
        return "int4"
    if operand is None or operand == ["null"]:
        return "null"
    if operand[0] == "integer":
        return "int4"
    if operand[0] == "wide-integer":
        return dict((v[1], name) for name, v in ATOMS.items() if v and v[0] == "wide-integer")[operand[1]]
    return {"slot": "old-int4", "wide-slot": "old-int8"}[operand[0]]


def integer(value):
    return ["integer", str(value)]


def filter_one(table_name=TARGET, value=1):
    return compare("=", ref("id", table_name), integer(value))


def mutation(tag, returning=RESULT):
    return {"dialect": "postgres", "release": tag, "target": table(TARGET),
            "returning": [[ref(name), alias] for name, alias in returning]}


def values(tag, names, supplied, returning=RESULT):
    return {**mutation(tag, returning), "written": [ref(name) for name in names], "rows": supplied}


def insert_query(tag, names, selected, *, source=None, where=None, distinct=False, returning=RESULT):
    root = query(tag, source, selected, where, distinct)
    return {**mutation(tag, returning), "written": [ref(name) for name in names], "insertQuery": root}


def update(tag, assignments, where=None, returning=RESULT):
    return {**mutation(tag, returning), "assignments": [[ref(name), value] for name, value in assignments], "where": where}


def delete(tag, where=None, returning=RESULT):
    return {**mutation(tag, returning), "where": where}


def finite(tag, branch, coords):
    if branch == "values":
        destination, value, mode = coords
        supplied = [[ATOMS[value]]] * (1 if mode == "single" else 2)
        return values(tag, [NAME[destination]], supplied)
    if branch == "query":
        destination, origin, distinct, filter_ = coords
        operand = ref(NAME[origin], INPUT)
        return insert_query(tag, [NAME[destination]], [[operand, "incoming"]], source=table(INPUT), distinct=distinct,
                            where=filter_one(INPUT) if filter_ == "in-range" else None)
    if branch == "update":
        destination, value, filter_ = coords
        operand = ref(NAME[value[4:]]) if value.startswith("old-") else ATOMS[value] or ["null"]
        return update(tag, [(NAME[destination], operand)], filter_one() if filter_ == "one" else None)
    form, label = coords
    fields = LAYOUTS[label]
    if form == "insert":
        return values(tag, ["wide"], [[ATOMS["int8-max"]]], fields)
    if form == "insert-query":
        return insert_query(tag, ["wide"], [[ATOMS["int8-max"], "value"]], returning=fields)
    if form == "update":
        return update(tag, [("wide", ATOMS["int8-min"])], filter_one(), fields)
    require(form == "delete", "postgres-signed-write-finite-statement", form)
    return delete(tag, filter_one(), fields)


def grids(contract):
    finite_ = contract["finite"]
    columns = {"values": ("destination", "operand", "mode"), "query": ("destination", "source", "distinct", "filter"),
               "update": ("destination", "operand", "filter"), "returning": ("statement", "layout")}
    for branch, names in columns.items():
        yield branch, [finite_[branch][name] for name in names]


def finite_ids(_, contract, _tag):
    result = set()
    for branch, axes in grids(contract):
        observed = {(branch, *coord) for coord in product(*axes)}
        require(len(observed) == contract["finite"][branch]["identities"], "postgres-signed-write-branch-size", branch)
        result.update(observed)
    require(len(result) == contract["finite"]["identities"], "postgres-signed-write-finite-size")
    return result


def kind(item):
    if "rows" in item:
        return "insert"
    if "insertQuery" in item:
        return "insert-query"
    return "update" if "assignments" in item else "delete"


def address(operand, binding):
    role, position = operand[:2]
    actual_name = binding["columns"][position]
    require(position >= 0 and (len(operand) == 2 or operand[2] == 0)
            and declared_kinds(binding)[position] == KINDS[actual_name] and role == FIELDS[KINDS[actual_name]],
            "postgres-signed-write-unowned-reference", [operand, binding])
    return actual_name


def finite_id(_, item, _expected):
    form, binding = kind(item), item["target"]
    if len(item["returning"]) == 2:
        fields = [(address(target, binding), label) for target, label in item["returning"]]
        return "returning", form, next(key for key, value in LAYOUTS.items() if value == fields)
    if form == "insert":
        (target,), inserted = item["written"], item["rows"]
        return "values", KINDS[address(target, binding)], atom_kind(inserted[0][0]), "single" if len(inserted) == 1 else "duplicate"
    if form == "insert-query":
        (target,), root = item["written"], item["insertQuery"]
        output, _ = root["columns"][0]
        return "query", KINDS[address(target, binding)], KINDS[address(output, root["source"])], root["distinct"], "in-range" if root["where"] else "all"
    require(form == "update", "postgres-signed-write-observed-statement", item)
    (target, operand), = item["assignments"]
    return "update", KINDS[address(target, binding)], atom_kind(operand), "one" if item["where"] else "all"


def analyzed(tag):
    wildcard = [(name, name) for name in HEADING[TARGET]]
    yield (f"INSERT INTO {T} (wide,id) VALUES ({MAX},5),({MIN},0) RETURNING wide,id",
           values(tag, ["wide", "id"], [[ATOMS["int8-max"], integer(5)], [ATOMS["int8-min"], integer(0)]], LAYOUTS["int8, int4"])
           | {"returning": [[ref("wide"), "wide"], [ref("id"), "id"]]})
    yield (f"INSERT INTO {T} (id) VALUES ({MAX}) RETURNING id",
           values(tag, ["id"], [[ATOMS["int8-max"]]], [("id", "id")]))
    yield (f"UPDATE {T} SET wide=id,id=wide WHERE id=1 RETURNING wide,id",
           update(tag, [("wide", ref("id")), ("id", ref("wide"))], filter_one(), [("wide", "wide"), ("id", "id")]))
    yield (f"UPDATE {T} SET id=wide WHERE id=2 RETURNING id",
           update(tag, [("id", ref("wide"))], filter_one(value=2), [("id", "id")]))
    yield (f"INSERT INTO {T} (wide,id) SELECT DISTINCT s.id AS i,s.wide AS w FROM {S} AS s WHERE s.id=1 RETURNING wide,id",
           insert_query(tag, ["wide", "id"], [[ref("id", INPUT), "i"], [ref("wide", INPUT), "w"]], source=table(INPUT),
                        where=filter_one(INPUT), distinct=True, returning=[("wide", "wide"), ("id", "id")]))
    yield (f"INSERT INTO {T} (wide) SELECT NULL AS v RETURNING wide",
           insert_query(tag, ["wide"], [[["null"], "v"]], returning=[("wide", "wide")]))
    yield (f"INSERT INTO {T} (wide) SELECT DISTINCT CAST(NULL AS bigint) AS v RETURNING wide",
           insert_query(tag, ["wide"], [[["builtin-cast", "int8", ["null"]], "v"]], distinct=True, returning=[("wide", "wide")]))
    yield (f"INSERT INTO {T} (note) VALUES ({MIN}) RETURNING note",
           values(tag, ["note"], [[ATOMS["int8-min"]]], [("note", "note")]))
    yield (f"DELETE FROM {T} WHERE id=2 RETURNING wide AS x, wide AS y",
           delete(tag, filter_one(value=2), [("wide", "x"), ("wide", "y")]))
    yield f"DELETE FROM {T} WHERE FALSE RETURNING *", delete(tag, ["boolean", False], wildcard)


def request(tag, sql, ddl=DDL):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": ddl}


def native_negatives():
    return (f"INSERT INTO {T} (wide) SELECT DISTINCT NULL AS v",
            f"INSERT INTO {T} (wide) SELECT CAST('one' AS pg_catalog.text) AS v")


def negatives(tag):
    def invalid(item, message):
        return item, {"exception": DIAGNOSTIC, "message": message}

    values_message = "An inserted cell must match its target native kind."
    yield invalid(values(tag, ["wide"], [[["boolean", True]]]), values_message)
    yield invalid(values(tag, ["id"], [[["text", "42"]]]), values_message)
    yield invalid(update(tag, [("wide", ref("enabled"))]), "An UPDATE assignment value must match its target native kind.")
    wrong_occurrence = update(tag, [("wide", ["wide-slot", 1, 1])])
    yield invalid(wrong_occurrence, "An UPDATE read slot must belong to occurrence zero.")
    wrong_kind = {**values(tag, ["wide"], [[ATOMS["int4"]]]), "written": [["wide-slot", 0, 0]]}
    yield invalid(wrong_kind, "An INSERT write slot must belong to the target.")
    yield invalid(insert_query(tag, ["wide"], [[["null"], "v"]], distinct=True), NULL_ERROR)
    untyped, text = native_negatives()
    yield invalid(request(tag, untyped), NULL_ERROR)
    yield invalid(request(tag, text), "Each query INSERT field requires a signed value or NULL.")
    yield invalid({**delete(tag), "returning": [[["wide-slot", 0, 0], "wrong"]]}, "A returned slot must belong to its target.")
    yield invalid(request(tag, "DELETE FROM public.ir_signed_other RETURNING v", ["CREATE TABLE public.ir_signed_other (v numeric)"]),
                  "The bound column must have the signed 32-bit integer type.")


def seeded(tag, seed, count):
    rng = random.Random(seed)
    for index in range(count):
        statement = rng.choice(("values", "query", "update", "delete"))
        selected = rng.sample(list(NAME.values()), rng.randrange(1, 4))
        atom_values = list(ATOMS.values())
        fields = [(rng.choice(tuple(KINDS)), rng.choice(("ambiguous", 'case " note', "値"))) for _ in range(rng.randrange(1, 5))]
        test = None if rng.getrandbits(1) else filter_one(value=rng.randrange(1, 5))
        if statement == "values":
            yield values(tag, selected, [[rng.choice(atom_values) for _ in selected] for _ in range(rng.randrange(1, 5))], fields)
        elif statement == "query":
            incoming = [[ref(rng.choice(("id", "wide")), INPUT), f"v{i}"] for i in range(len(selected))]
            yield insert_query(tag, selected, incoming, source=table(INPUT), distinct=bool(rng.getrandbits(1)),
                               where=filter_one(INPUT, rng.randrange(1, 5)) if test is not None else None, returning=fields)
        elif statement == "update":
            values_ = [*atom_values, ref("id"), ref("wide")]
            yield update(tag, [(name, rng.choice(values_) or ["null"]) for name in selected], test, fields)
        else:
            yield delete(tag, test, fields)


def requests(_, tag, contract):
    index = 0
    for branch, axes in grids(contract):
        for coords in product(*axes):
            item = finite(tag, branch, coords)
            yield f"finite/{index}", item, item, None
            index += 1
    examples = list(analyzed(tag))
    require(len(examples) == len(contract["analysis"]), "postgres-signed-write-analysis-count")
    for index, (sql, item) in enumerate(examples):
        yield f"analyze/{index}", request(tag, sql), item, None
    for seed in contract["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, contract["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, invalid) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, invalid


def bound(operand, binding):
    if type(operand) is str:
        return "integer", operand
    if operand is None:
        return None
    role = operand[0]
    if role in FIELDS.values():
        return role, address(operand, binding)
    if role == "builtin-cast":
        return role, operand[1], bound(operand[2], binding)
    if role == "comparison":
        return role, operand[1], bound(operand[2], binding), bound(operand[3], binding)
    require(role in ("null", "integer", "wide-integer", "boolean"), "postgres-signed-write-unknown-term", operand)
    return tuple(operand)


def declared_kinds(source):
    return source.get("columnKinds", ["int4"] * len(source["columns"]))


def binding(source, identity):
    require(source["schema"] == "public" and source["table"] == identity and not source["only"]
            and len(source["columns"]) == len(declared_kinds(source)) and len(set(source["columns"])) == len(source["columns"])
            and all(KINDS[actual] == native for actual, native in zip(source["columns"], declared_kinds(source))),
            "postgres-signed-write-unexpected-relation", source)
    return source


def graph(item):
    destination = binding(item["target"], TARGET)
    shape = [kind(item), tuple((bound(term, destination), name) for term, name in item["returning"])]
    if "written" in item:
        shape.append(tuple(bound(term, destination) for term in item["written"]))
    if "rows" in item:
        shape.append(tuple(tuple(bound(term, None) for term in row) for row in item["rows"]))
    elif "insertQuery" in item:
        root = item["insertQuery"]
        source = binding(root["source"], INPUT) if root["source"] is not None else None
        require(not root["orderBy"] and root["limit"] is None, "postgres-signed-write-bounded-source")
        shape.extend((source is not None, tuple((bound(term, source), name) for term, name in root["columns"]),
                      bound(root["where"], source), root["distinct"]))
    elif "assignments" in item:
        shape.append(tuple((bound(target, destination), bound(value, destination)) for target, value in item["assignments"]))
    return shape, bound(item.get("where"), destination)


def checked_assign(value, native):
    if value is None:
        return None
    if native == "text":
        return str(value)
    require(type(value) is int, "postgres-signed-write-oracle-nonnative-operand", value)
    if native == "int4" and not -(2**31) <= value <= 2**31 - 1:
        raise OverflowError()
    return value


def read(operand, current, source):
    if type(operand) is str:
        return int(operand)
    if operand is None or operand == ["null"]:
        return None
    role = operand[0]
    if role in FIELDS.values():
        return current[address(operand, source)]
    if role == "builtin-cast":
        return checked_assign(read(operand[2], current, source), operand[1])
    if role == "boolean":
        return operand[1]
    require(role in ("integer", "wide-integer"), "postgres-signed-write-oracle-field", operand)
    return int(operand[1])


def allows(term, row, source):
    return admits(term, lambda operand: read(operand, row, source))


def after_assignment(old, names, values_):
    created = dict(old)
    for name, value in zip(names, values_):
        created[name] = checked_assign(value, KINDS[name])
    return created


def mutate(item):
    target = item["target"]
    before = [dict(zip(KINDS, row)) for row in INITIAL]
    if "written" in item:
        names = [address(term, target) for term in item["written"]]
        if "rows" in item:
            projected = [[read(term, {}, None) for term in row] for row in item["rows"]]
        else:
            selection = item["insertQuery"]
            source = selection["source"]
            inputs = [{}] if source is None else [dict(zip(HEADING[INPUT], row)) for row in INPUT_ROWS]
            projected = [tuple(read(term, row, source) for term, _ in selection["columns"]) for row in inputs
                         if allows(selection["where"], row, source)]
            if selection["distinct"]:
                projected = list(dict.fromkeys(projected))
        inserted = [after_assignment(DEFAULTS, names, row) for row in projected]
        return inserted, [*before, *inserted]
    modified = []
    after = []
    for old in before:
        if not allows(item["where"], old, target):
            after.append(old)
            continue
        if "assignments" not in item:
            modified.append(old)
            continue
        names = [address(term, target) for term, _ in item["assignments"]]
        values_ = [read(value, old, target) for _, value in item["assignments"]]
        new = after_assignment(old, names, values_)
        after.append(new)
        modified.append(new)
    return modified, after


def native_error(state):
    return {"status": "db-error", "state": state, "before": INITIAL, "restored": INITIAL,
            "inputBefore": INPUT_ROWS, "inputRestored": INPUT_ROWS}


def expected(item):
    try:
        affected, after = mutate(item)
    except OverflowError:
        return native_error("22003")
    data = {"status": "mutation", "count": len(affected), "after": [[row[name] for name in KINDS] for row in after],
            "inputAfter": INPUT_ROWS, "before": INITIAL, "restored": INITIAL, "inputBefore": INPUT_ROWS, "inputRestored": INPUT_ROWS}
    if not item["returning"]:
        return data
    selected = [(address(term, item["target"]), name) for term, name in item["returning"]]
    return {**data, "fields": [[label, KINDS[name], OID[KINDS[name]]] for name, label in selected],
            "rows": [[row[name] for name, _ in selected] for row in affected]}


def observation(raw):
    row_keys = {"before", "after", "rows", "restored", "inputBefore", "inputAfter", "inputRestored"}
    return {key: sorted([list(row) for row in value], key=canonical) if key in row_keys else value for key, value in raw.items()}


def prepare(_, db):
    outside = (f"UPDATE {S} SET wide = 0", f"DELETE FROM {T}; DELETE FROM {S}",
               f"INSERT INTO {T} (wide) SELECT id FROM {T}", f"DELETE FROM {T} RETURNING current_database()")
    return {"excluded": [db.request({"sql": sql}) for sql in outside],
            "nativeErrors": [observation(db.request({"sql": sql})) for sql in native_negatives()]}


def check_preparation(_, environment, result):
    require(environment["database"][-1] == "pg_catalog, public", "postgres-signed-write-profile")
    same(observation({"before": INITIAL, "inputBefore": INPUT_ROWS}),
         observation({"before": environment["initial"], "inputBefore": environment["input"]}))
    same([{"status": "outside-observer-scope"}] * 4, result["excluded"])
    same([observation(native_error("42804"))] * 2, result["nativeErrors"])


def observer_request(_, sql):
    return {"sql": sql}


def check_value(item, response):
    require(response.get("status") == "constructed" and response["profile"].startswith(item["release"] + "/"),
            "postgres-signed-write-positive-rejected", response)
    require(graph(item) == graph(response), "postgres-signed-write-graph", {"input": item, "response": response})


def check_observation(item, actual, sql):
    prediction = observation(expected(item))
    try:
        same(prediction, actual)
    except Failure as error:
        raise Failure(error.signal, {"sql": sql, "expected": prediction, "observed": actual}) from error


def required_forms(*_):
    return {"insert", "insert-query", "update", "delete", "multiple-values", "multiple-targets", "wide-output", "text-target",
            "narrowing", "reordered", "old-row", "distinct/True", "distinct/False", "filter/True", "filter/False"}


def sample_forms(forms, reply, *_):
    form = kind(reply)
    forms[form] += 1
    forms[f"filter/{reply.get('where', reply.get('insertQuery', {}).get('where')) is not None}"] += 1
    if "rows" in reply and len(reply["rows"]) > 1:
        forms["multiple-values"] += 1
    if "insertQuery" in reply:
        forms[f"distinct/{reply['insertQuery']['distinct']}"] += 1
    written = reply.get("written", [assignment[0] for assignment in reply.get("assignments", [])])
    if len(written) > 1:
        forms["multiple-targets"] += 1
        names = [address(term, reply["target"]) for term in written]
        if names != sorted(names, key=HEADING[TARGET].index):
            forms["reordered"] += 1
    if any(term[0] == "text-slot" for term in written):
        forms["text-target"] += 1
    if any(term[0] == "wide-slot" for term, _ in reply["returning"]):
        forms["wide-output"] += 1
    if any(isinstance(term, list) and term[0] in FIELDS.values() for _, term in reply.get("assignments", [])):
        forms["old-row"] += 1
    if expected(reply)["status"] == "db-error":
        forms["narrowing"] += 1


def controls():
    item = values("pg-17.2", ["wide"], [[ATOMS["int8-max"]]] * 2)
    prediction = observation(expected(item))
    check_observation(item, prediction, "healthy")
    altered = {"drop duplicate": {**prediction, "rows": prediction["rows"][1:]},
               "wrong width": {**prediction, "fields": [["stored", "int4", 23], *prediction["fields"][1:]]},
               "bad rollback": {**prediction, "restored": prediction["after"]}}
    detections = {}
    for name, actual in altered.items():
        try:
            check_observation(item, actual, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-signed-write-unexpected-control", name)
            detections[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-signed-write-comparator-accepted", name)
    return detections


def check_negative(item, response):
    if rejects(response, item["boundary"]):
        return
    require(response.get("status") == "constructed", "postgres-signed-write-unexpected-negative-reply", response)
    raise Failure("postgres-signed-write-untyped-distinct", {"sql": response["sql"], "actual": response, "expected": item["boundary"]})


def fault_probe(item, response, db):
    if "boundary" in item:
        check_negative(item, response)
        return
    probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            check_negative(rule.item, record["healthyResponse"])
            check_negative(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            require(witness["observed"]["status"] == "mutation" or witness["observed"] == observation(native_error("22003")),
                    "postgres-signed-write-fault-incorrect-observation", witness)
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-signed-write-undetected-fault", rule.name)


def faults(_, tag):
    ir = "sql-semantics-postgres/src/Ir/"
    minimum = values(tag, ["wide"], [[ATOMS["int8-min"]]])
    around = values(tag, ["wide", "note"], [[ATOMS["int8-max"], ATOMS["int4"]]])
    swap = update(tag, [("wide", ref("id")), ("id", ref("wide"))], filter_one())
    duplicates = finite(tag, "query", ["int8", "int8", True, "in-range"])
    invalid, boundary = list(negatives(tag))[5]
    return [
        RenderFault("narrow-int8-supplied-cell", ir + "Mutation/InsertCell.php", "' AS pg_catalog.int8)'", "' AS pg_catalog.int4)'",
                    minimum, "meaning-mismatch"),
        RenderFault("reverse-signed-destination-order", ir + "Mutation/Insert/Destination.php", "implode(', ', $columns)", "implode(', ', array_reverse($columns))",
                    around, "meaning-mismatch"),
        RenderFault("assign-signed-target-old-value", ir + "Mutation/Update.php", "$sql->expression($assignment->value, $this->target)",
                    "$sql->expression($assignment->target, $this->target)", swap, "meaning-mismatch"),
        RenderFault("remove-distinct-before-signed-write", ir + "SelectSql.php", "'SELECT DISTINCT '", "'SELECT '",
                    duplicates, "meaning-mismatch"),
        RenderFault("return-int4-instead-of-int8", ir + "Mutation/ReturningSql.php",
                    "$target->columns[$field->reference->slot]->quoted()",
                    "($target->columns[$field->reference->slot]->value === 'wide' ? '\"id\"' : $target->columns[$field->reference->slot]->quoted())",
                    minimum, "meaning-mismatch"),
        RenderFault("type-unknown-before-distinct", ir + "Mutation/InsertQuery.php",
                    "!$query->distinct || !$value instanceof NullLiteral", "true",
                    {"boundary": boundary}, "postgres-signed-write-untyped-distinct", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
