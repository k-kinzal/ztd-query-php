"""Compare native MySQL mutations filtered by VARCHAR presence and their trigger effects."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main
from run_server_mysql_varchar_presence import logical, null_test
from run_server_mysql_varchar_slots import (
    DATA, DIAGNOSTICS, HEADING, LENGTHS, PRIMARY, SCHEMA, SECONDARY, TYPES,
    check_preparation as check_fixture, field, prepare as prepare_fixture, query, table,
)
from server_join_cases import compare, slot
from server_truth_model import admits
from sqlite_domain import RenderFault

LABEL = "mysql-varchar-effects"
CONTRACT = "7d166c495706eaa796ae11f08233c6c23e026caa1bfd723813e94100294729a2"


def specifications():
    path, profile = TOOL / "mysql-varchar-effects-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-varchar-effects-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def observer_request(_item, sql):
    return {"sql": sql, "observeMysqlVarcharEffect": True}


def prepare(engine, db):
    source = prepare_fixture(engine, db)
    events = f"{SCHEMA}.ir_varchar_events"
    commands = [
        f"DROP TABLE IF EXISTS {events}",
        f"CREATE TABLE {events} (relation_name VARCHAR(64), operation_name VARCHAR(8), "
        "old_label VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin, old_id INTEGER, new_id INTEGER) ENGINE=InnoDB",
    ]
    for table_name in DATA:
        for operation, new in (("DELETE", "NULL"), ("UPDATE", "NEW.id")):
            commands.append(f"CREATE TRIGGER {SCHEMA}.{table_name}_{operation.lower()} AFTER {operation} "
                            f"ON {SCHEMA}.{table_name} FOR EACH ROW INSERT INTO {events} "
                            f"VALUES ('{table_name}', '{operation.lower()}', OLD.label, OLD.id, {new})")
    triggers = db.request({"fixture": commands})
    storage = db.request({"sql": "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES "
                                f"WHERE TABLE_SCHEMA = '{SCHEMA}' AND TABLE_NAME IN ('{PRIMARY}', '{SECONDARY}', 'ir_varchar_events') "
                                "ORDER BY TABLE_NAME"})
    bad_sql = (f"DELETE FROM {SCHEMA}.another_relation",
               f"UPDATE {SCHEMA}.{PRIMARY} SET label = 'other'",
               f"DELETE FROM {SCHEMA}.{PRIMARY}; DELETE FROM {SCHEMA}.{SECONDARY}")
    blocked = [db.request(observer_request(None, sql)) for sql in bad_sql]
    statement = f"UPDATE {SCHEMA}.{PRIMARY} SET id = id WHERE id = 2"
    no_change = observation(db.request(observer_request(None, statement)))
    return {"source": source, "triggers": triggers, "storage": storage, "blocked": blocked, "noChange": no_change}


def check_preparation(engine, environment, prepared):
    check_fixture(engine, environment, prepared["source"])
    same({"status": "fixture"}, prepared["triggers"])
    same([{"status": "outside-observer-scope"}] * 3, prepared["blocked"])
    require(prepared["storage"]["status"] == "rows", "mysql-varchar-effects-storage", prepared["storage"])
    same([[name, "InnoDB"] for name in sorted(("ir_varchar_events", *DATA))], prepared["storage"]["rows"])
    positive = statement("mysql-9.1.0", "update", compare("=", slot(1), integer(2)), "same")
    same(expected(positive), prepared["noChange"])


def integer(number):
    return ["integer", str(number)]


def statement(tag, operation, predicate, update=None):
    target = table()
    base = {"dialect": "mysql", "release": tag, "target": target, "where": predicate}
    if operation == "delete":
        return base
    require(operation == "update", "mysql-varchar-effects-statement-role", operation)
    assignment = {"changed": integer(9901), "same": slot(1), "null": ["null"]}[update]
    return {**base, "assignments": [[slot(1), assignment]]}


def finite(tag, operation, operand, polarity, update=None):
    return statement(tag, operation, null_test(field(HEADING.index(operand)), polarity == "not-null"), update)


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    identities = {("delete", operand, polarity, "") for operand, polarity in product(b["operands"], b["polarities"])}
    identities.update(("update", *point) for point in product(b["operands"], b["polarities"], b["updateValues"]))
    require(len(identities) == b["identities"], "mysql-varchar-effects-finite-size", len(identities))
    return identities


def role(item):
    return "update" if "assignments" in item else "delete"


def address(term, target):
    name = target["columns"][term[1]]
    occurrence = term[2] if len(term) > 2 else 0
    native = target.get("columnTypes", ["int"] * len(target["columns"]))[term[1]]
    require(occurrence == 0 and native == TYPES[name] and (term[0] == "text-slot") == (native != "int"),
            "mysql-varchar-effects-address-kind", [term, target])
    return name


def graph_term(term, target):
    if term is None:
        return None
    key = term[0]
    if key in ("slot", "text-slot"):
        return key, address(term, target)
    if key == "null-test":
        return key, term[1], graph_term(term[2], target)
    if key in ("logic", "comparison"):
        return key, term[1], graph_term(term[2], target), graph_term(term[3], target)
    if key == "text-comparison":
        return key, term[1], term[2], graph_term(term[3], target), graph_term(term[4], target)
    require(key in ("null", "integer", "boolean", "text"), "mysql-varchar-effects-reachable-term", term)
    return tuple(term)


def graph(item):
    target = item["target"]
    assignment = tuple((address(slot_, target), graph_term(value, target)) for slot_, value in item.get("assignments", ()))
    return target["schema"], target["table"], role(item), graph_term(item["where"], target), assignment


def finite_id(_, response, _item):
    _, name, operation, test, assignment = graph(response)
    require(name == PRIMARY and test[0] == "null-test" and test[2][0] == "text-slot",
            "mysql-varchar-effects-finite-predicate", response)
    update = ""
    if operation == "update":
        (destination, atom), = assignment
        require(destination == "id", "mysql-varchar-effects-finite-destination", response)
        update = {"slot": "same", "null": "null", "integer": "changed"}[atom[0]]
    return operation, test[2][1], "not-null" if test[1] else "null", update


def analyzed(tag):
    name = f"{SCHEMA}.{PRIMARY}"
    yield f"DELETE FROM {name} WHERE alternate IS NULL", finite(tag, "delete", "alternate", "null")
    yield f"UPDATE {name} SET id=9901 WHERE label IS NOT NULL", finite(tag, "update", "label", "not-null", "changed")
    where = logical("AND", null_test(field(3), True), compare(">=", slot(1), integer(2)))
    yield (f"UPDATE {name} AS r SET id = r.id WHERE r.empty_value IS NOT NULL AND r.id >= 2",
           statement(tag, "update", where, "same"))


def negative(tag):
    scope = "A VARCHAR slot must belong to a visible physical input occurrence."
    yield statement(tag, "delete", null_test(field(1))), scope
    yield statement(tag, "update", null_test(field(0, 1)), "changed"), scope
    item = statement(tag, "update", null_test(field(0)), "changed")
    yield {**item, "assignments": [[slot(0), integer(1)]]}, "An UPDATE write slot must belong to the target."


def seeded(tag, seed, count):
    rng = Random(seed)
    names = tuple(LENGTHS)
    for index in range(count):
        operation = "delete" if index % 2 else "update"
        terms = [null_test(field(HEADING.index(rng.choice(names))), bool(rng.getrandbits(1)))
                 for _ in range(rng.randint(1, 4))]
        first, *rest = terms
        for following in rest:
            first = logical(rng.choice(("AND", "OR", "XOR")), first, following)
        if index % 3 == 0:
            first = logical("NOT", first)
        yield statement(tag, operation, first, rng.choice(("same", "changed", "null")) if operation == "update" else None)


def requests(_, tag, spec):
    b = spec["finite"]
    count = 0
    for operation in b["operations"]:
        assignments = (None,) if operation == "delete" else b["updateValues"]
        for operand, polarity, update in product(b["operands"], b["polarities"], assignments):
            item = finite(tag, operation, operand, polarity, update)
            yield f"finite/{count}", item, item, None
            count += 1
    cases = list(analyzed(tag))
    require(len(cases) == len(spec["analysis"]), "mysql-varchar-effects-analysis-incomplete")
    for index, (sql, item) in enumerate(cases):
        yield f"analysis/{index}", query(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negative(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def observation(raw):
    def ordered(snapshot):
        return {name: sorted((list(row) for row in rows), key=json.dumps) for name, rows in snapshot.items()}

    return {key: ordered(value) if key in ("before", "after", "restored") else value for key, value in raw.items()}


def expected(item):
    target = item["target"]
    before = {"parent": DATA[PRIMARY], "control": DATA[SECONDARY], "events": []}
    rows, events = [], []
    count = 0
    operation = role(item)

    def atom(term, row):
        if term[0] == "integer":
            return int(term[1])
        if term[0] == "text":
            return term[1]
        if term[0] == "null":
            return None
        return row[HEADING.index(address(term, target))]

    for row in before["parent"]:
        if not admits(item["where"], lambda term: atom(term, row)):
            rows.append(row)
            continue
        if operation == "delete":
            count += 1
            new = None
        else:
            (dest, term), = item["assignments"]
            require(address(dest, target) == "id", "mysql-varchar-effects-oracle-write-slot")
            new = atom(term, row)
            rows.append((row[0], new, *row[2:]))
            count += int(row[1] != new)
        events.append((PRIMARY, operation, row[0], row[1], new))
    after = {"parent": rows, "control": before["control"], "events": events}
    return observation({"status": "mutation", "columns": 0, "count": count, "before": before, "after": after, "restored": before})


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "mysql-varchar-effects-positive-rejected", reply)
    require(graph(item) == graph(reply), "mysql-varchar-effects-wrong-statement", {"wanted": item, "observed": reply})


def check_observation(item, raw, sql):
    target = expected(item)
    witness = {"sql": sql, "expected": target, "observed": raw}
    require(raw["status"] == "mutation", "mysql-varchar-effects-native-rejection", witness)
    require(target == raw, "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "mutation", "mysql-varchar-effects-fault-native")


def sample_forms(forms, reply, *_):
    _, _, operation, predicate, assignment = graph(reply)
    forms["operation/" + operation] += 1
    if assignment:
        forms["assignment/" + assignment[0][1][0]] += 1

    def visit(term):
        if term is None:
            return
        if term[0] == "logic":
            forms["logic/" + term[1]] += 1
            visit(term[2])
            visit(term[3])
        elif term[0] == "null-test":
            forms[f"presence/{term[1]}/{term[2][1]}"] += 1
        else:
            raise Failure("mysql-varchar-effects-unsampled-role", term)

    visit(predicate)


def required_forms(*_):
    return ({"operation/delete", "operation/update", "assignment/slot", "assignment/null", "assignment/integer",
             "logic/AND", "logic/OR", "logic/XOR", "logic/NOT"}
            | {f"presence/{polarity}/{name}" for polarity, name in product((True, False), LENGTHS)})


def controls():
    item = finite("mysql-9.1.0", "update", "label", "not-null", "same")
    target = expected(item)
    changed = {**target, "count": len(target["after"]["events"])}
    require(changed != target, "mysql-varchar-effects-control-needs-no-op-matches")
    check_observation(item, target, "matching rows fire triggers without counting as changed")
    try:
        check_observation(item, changed, "report matched rows as changed")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "mysql-varchar-effects-wrong-control", failure.detail)
    else:
        raise Failure("mysql-varchar-effects-comparator-accepted")
    return {"input": item, "expected": target, "mismatch": changed}


def faults(_, tag):
    src = "sql-semantics-mysql/src/Ir/"
    select = src + "SelectSql.php"
    delete = src + "Mutation/Delete.php"
    update = src + "Mutation/Update.php"
    target = finite(tag, "delete", "label", "null")
    changed = finite(tag, "update", "label", "not-null", "changed")
    quiet = finite(tag, "update", "label", "not-null", "same")
    quote = chr(96)
    other = f"'{quote}{SCHEMA}{quote}.{quote}{SECONDARY}{quote} AS {quote}__ztd_source{quote}'"
    return [
        RenderFault("drop-varchar-delete-filter", delete, "if ($this->where === null)", "if (true)", target, "meaning-mismatch"),
        RenderFault("invert-varchar-mutation-filter", select, "$condition->negated ?", "!$condition->negated ?", target, "meaning-mismatch"),
        RenderFault("read-the-other-varchar-filter-slot", select, "$input->columns[$expression->slot]->quoted()",
                    "$input->columns[$expression instanceof TextColumnReference ? 2 : $expression->slot]->quoted()",
                    target, "meaning-mismatch"),
        RenderFault("mutate-varchar-control-relation", update, "$sql->from($this->target)", other, changed, "meaning-mismatch"),
        RenderFault("make-a-varchar-no-op-change-the-row", update, "$sql->expression($assignment->value, $this->target)",
                    "'9901'", quiet, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
