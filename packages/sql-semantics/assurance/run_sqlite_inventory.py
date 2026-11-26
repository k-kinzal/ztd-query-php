"""Compare SQLite connection registries with independent native API and source data."""
import copy
import ctypes as c
import hashlib
import json
from pathlib import Path
import stat
import sys
from tempfile import gettempdir

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_inventory_cases import (
    HEADINGS, UNORDERED, analyzed, case, expected, finite_id, finite_ids, forms, negatives, requests, required, sql_for,
)
from sqlite_pragma_inventory import ENGINE_SOURCE
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-connection-inventory-v1.json"
CONTRACT = "d1d7183b5a3f85b86723cc188e1707d9eedfefc21327d9da850470fcbf62ba89"
REGISTRY = "5f35a7d26a0ed668f89465b642ac2ded2a8d9747238aa61117e62dfd81ab1616"
NAMESPACE = "ba957109ec9febbe65ec7874f647c0549233c7617166eed9d613315042dba010"
FIXTURE = ""
OPERATIONS = frozenset(name.encode() for name in HEADINGS)
COLLATION = c.CFUNCTYPE(c.c_int, c.c_void_p, c.c_int, c.c_void_p, c.c_int, c.c_void_p)
SCALAR = c.CFUNCTYPE(None, c.c_void_p, c.c_int, c.c_void_p)


def checked_record(name, expected_digest):
    raw = Path(__file__).with_name(name).read_bytes()
    require(hashlib.sha256(raw).hexdigest() == expected_digest, "sqlite-inventory-basis-digest", name)
    return json.loads(raw)


def header_file():
    # A stable, empty native fixture keeps raw path observations reproducible.
    path = Path(gettempdir()).resolve() / "ztd-query-sqlite-inventory-empty-v1"
    try:
        with path.open("xb"):
            pass
    except FileExistsError:
        pass
    details = path.lstat()
    require(stat.S_ISREG(details.st_mode) and details.st_size == 0, "sqlite-inventory-file-fixture", str(path))
    return str(path)


class InventoryDatabase(Database):
    """Observe only the closed SQLite registry family with the verified native APIs."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}:{REGISTRY}:{NAMESPACE}".encode()).hexdigest()

    def __init__(self, library, base=None):
        super().__init__(library)
        self.schemata = frozenset()
        self.calls = []
        p, i, s = c.c_void_p, c.c_int, c.c_char_p
        self.db_name = self.lib.sqlite3_db_name
        self.db_name.argtypes, self.db_name.restype = (p, i), s
        self.db_filename = self.lib.sqlite3_db_filename
        self.db_filename.argtypes, self.db_filename.restype = (p, s), s
        self.compile_option = self.lib.sqlite3_compileoption_get
        self.compile_option.argtypes, self.compile_option.restype = (i,), s
        self.callbacks = ()
        if base is None:
            self.registry = checked_record("sqlite-native-registry-v1.json", REGISTRY)
            native_driver = Path(__file__).with_name("sqlite_native_registry.c")
            require(hashlib.sha256(native_driver.read_bytes()).hexdigest() == self.registry["driver"],
                    "sqlite-inventory-registry-driver-digest")
            require(self.source == self.registry["source"] == ENGINE_SOURCE, "sqlite-inventory-native-source")
            namespace = checked_record("sqlite-pragma-namespace-v1.json", NAMESPACE)
            self.options = self.compile_options()
            self.profile = "darwin" if sys.platform == "darwin" else "linux" if sys.platform.startswith("linux") else None
            require(self.profile in namespace["profiles"], "sqlite-inventory-unknown-library-platform", sys.platform)
            profile = namespace["profiles"][self.profile]
            require(without_compiler(profile["compileOptions"]) == without_compiler(self.options),
                    "sqlite-inventory-unapproved-registry-build", self.options)
            self.pragma_names = profile["activeNames"]
        else:
            self.registry, self.options, self.pragma_names, self.profile = base.registry, base.options, base.pragma_names, base.profile

    def authorize(self, _, action, first, second, schema, origin):
        if origin is None and action == 19 and first is not None and first.lower() in OPERATIONS and schema in self.schemata:
            self.calls.append([first.decode().lower(), None if schema is None else schema.decode(),
                               None if second is None else second.decode()])
            return 0
        if self.denied is None:
            self.denied = (action, [value.decode(errors="replace") if value is not None else None
                                    for value in (first, second, schema, origin)])
        return 1

    def registrations(self):
        rows = []
        for slot in range(64):
            name = self.db_name(self.db, slot)
            if name is None:
                return rows
            file = self.db_filename(self.db, name)
            rows.append([slot, name.decode(), None if file is None else file.decode()])
        raise ValueError("Fixture attachment bound exhausted")

    def compile_options(self):
        options = []
        for ordinal in range(512):
            option = self.compile_option(ordinal)
            if option is None:
                return options
            options.append(option.decode())
        raise ValueError("Native compiler option bound exhausted")

    def install(self):
        p, i, s = c.c_void_p, c.c_int, c.c_char_p
        create_collation = self.lib.sqlite3_create_collation_v2
        create_collation.argtypes = (p, s, i, p, COLLATION, p)
        comparison = COLLATION(compare_text)
        for name in (b"local_key", b"Case Sort", b"rtrim"):
            self.check(create_collation(self.db, name, 1, None, comparison, None))
        create_function = self.lib.sqlite3_create_function_v2
        create_function.argtypes = (p, s, i, i, p, SCALAR, p, p, p)
        assign = self.lib.sqlite3_result_int
        assign.argtypes, assign.restype = (p, i), None
        scalar = SCALAR(lambda context, _count, _values: assign(context, 42))
        for function, arity, flags in ((b"local_query", 2, 0x200800), (b"abs", 1, 0x80000)):
            self.check(create_function(self.db, function, arity, flags | 1, None, scalar, None, None, None))
        self.callbacks = (comparison, scalar)


def without_compiler(options):
    return [option for option in options if not option.startswith("COMPILER=")]


def compare_text(_context, length_left, left, length_right, right):
    a, b = c.string_at(left, length_left), c.string_at(right, length_right)
    return (a > b) - (a < b)


def setup(db, item, filename):
    name = item["attachment"]
    db.execute("ATTACH '" + filename.replace("'", "''") + "' AS " + quoted(name))
    db.execute("CREATE TABLE fixture(v TEXT)")
    if item["tempOpen"]:
        db.execute("CREATE TEMP TABLE opened(a INT)")
    if item["registry"] == "extended":
        db.install()
    if item["registry"] == "lazy":
        native = db._observe("SELECT name FROM pragma_table_info('fixture')", {})
        require(native["rows"] == [[["text", "v"]]], "sqlite-inventory-module-registration", native)
    db.schemata = frozenset((None, b"main", b"temp", name.encode()))


def state(db):
    try:
        value = db._observe("SELECT local_query(1,2)", {})["rows"]
        require(value == [[["integer", "42"]]], "sqlite-inventory-registered-scalar", value)
        return "extended"
    except RuntimeError as error:
        if str(error) != "SQLite 1: no such function: local_query":
            raise
    modules = db._observe("PRAGMA module_list", {})["rows"]
    return "lazy" if [["text", "pragma_table_info"]] in modules else "stock"


def observe(template, item, sql, filename):
    db = InventoryDatabase(template.library, template)
    try:
        setup(db, item, filename)
        registration = state(db)
        before = db.registrations()
        statement = db.observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
        require(len(db.calls) == 1, "sqlite-inventory-single-native-operation", db.calls)
        pragma, scope, argument = db.calls[0]
        return {"registration": registration, "before": before, "after": db.registrations(),
                "statement": statement, "invocation": [pragma, scope], "raw": [pragma, scope, argument]}
    finally:
        db.check(db.close(db.db))


def semantic(observation):
    checked = {name: value for name, value in observation.items() if name != "raw"}
    if checked["invocation"][0] in UNORDERED:
        checked["statement"] = {**checked["statement"], "rows": sorted(checked["statement"]["rows"])}
    return checked


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "connection-inventory"
            and all(reply.get(field) == item[field] for field in ("pragma", "schema")),
            "sqlite-inventory-structure", {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    filename = header_file() if item["file"] else ""
    measurements = {"observation": observe(db, item, reply["sql"], filename)}
    if original is not None:
        measurements["original"] = observe(db, item, original, filename)
    if ledger is not None:
        ledger.record(key, "observed", **measurements)
    prediction = expected(item, db.registry, db.options, db.pragma_names, filename)
    for measurement in measurements.values():
        compare(prediction, semantic(measurement))
    if ledger is not None:
        ledger.record(key, "compared")
    return measurements.get("original", measurements["observation"])


def controls(db):
    item = case("function_list", "temp", "extended")
    healthy = observe(db, item, "PRAGMA temp.function_list", "")
    want = expected(item, db.registry, db.options, db.pragma_names, "")
    compare(want, semantic(healthy))
    dropped_override, wrong_arity = copy.deepcopy(healthy), copy.deepcopy(healthy)
    dropped_override["statement"]["rows"] = [
        row for row in dropped_override["statement"]["rows"] if row[:2] != [["text", "abs"], ["integer", "0"]]
    ]
    marker = next(row for row in wrong_arity["statement"]["rows"] if row[0] == ["text", "local_query"])
    marker[4] = ["integer", "1"]
    rejected = {}
    for label, observation in (("lost-overridden-function", dropped_override), ("changed-arity", wrong_arity)):
        try:
            compare(want, semantic(observation))
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-inventory-comparator-signal", failure.signal)
            rejected[label] = {"witness": observation, "signal": failure.signal}
        else:
            raise Failure("sqlite-inventory-comparator-accepted", label)
    return {"healthy": healthy, "rejected": rejected}


def execute(args, contract, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    checks = execute_schedule(args, contract, db, worker_factory, schedule, probe,
                              measured_input=lambda reply, observation: (reply, observation))
    return {**checks, "reference": {"registry": REGISTRY, "registryDriver": db.registry["driver"], "nativeNamespace": NAMESPACE,
                                     "compilerOptions": db.options, "namespaceProfile": db.profile}}


def fault_probe(item, reply, db):
    if item.get("refusal"):
        require(reply["status"] == "rejected" and item["refusal"] in reply.get("message", ""),
                "sqlite-inventory-accepted-unknown-schema", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/Ir/Maintenance/"
    output, scope, routing = root + "Inventory/InventoryPragma.php", root + "PragmaScope.php", root + "PragmaLowering.php"
    native, list_scope, raw = case("function_list"), case("database_list", "temp"), case("collation_list", argument="number")
    return runner(args, db, [
        RenderFault("select-wrong-connection-registry", output, "$this->kind->value",
                    "InventoryKind::Collations->value", native, "meaning-mismatch"),
        RenderFault("ignore-inventory-access-qualifier", output, "$qualifier . $this->kind->value",
                    "$this->kind->value", list_scope, "meaning-mismatch"),
        RenderFault("emit-constant-native-inventory", output, "'PRAGMA ' . $qualifier . $this->kind->value",
                    "'PRAGMA pragma_list'", list_scope, "meaning-mismatch"),
        RenderFault("allow-unknown-inventory-schema", scope, "if ($qualifier === null) {", "if (true) {",
                    {**native, "refusal": "requires a known schema qualifier"}, "sqlite-inventory-accepted-unknown-schema",
                    analyzed(native, "PRAGMA absent.function_list")),
        RenderFault("refuse-native-ignored-inventory-argument", routing, "if ($inventory !== null) {",
                    "if ($inventory !== null && $statement->value === null) {", raw, "sqlite-inventory-structure",
                    analyzed(raw, sql_for(raw, "+1"))),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("connection-inventory", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, InventoryDatabase)
