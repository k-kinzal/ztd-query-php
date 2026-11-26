"""SQLite observations through the C interface of the specified engine build."""

import ctypes as c
import hashlib
import json
from pathlib import Path

OBSERVER_CONTRACT = "a3c8c144a57d14e01005bc7853ee084dff06ebed03bc4ebe69b2413c7eeb9250"
TEXT_OBSERVATION_CONTRACT = "adc536e31e0fb7ee8ef3cd6edf8f83aed44938bebc551711dc0b6f80cdbf0591"
AUTHORIZER = c.CFUNCTYPE(c.c_int, c.c_void_p, c.c_int, *([c.c_char_p] * 4))


def observer_policy():
    data = Path(__file__).with_name("sqlite-observer-v1.json").read_bytes()
    if hashlib.sha256(data).hexdigest() != OBSERVER_CONTRACT:
        raise ValueError("SQLite observer policy differs from its recorded revision")
    return json.loads(data)


class ObserverAuthorizationError(RuntimeError):
    def __init__(self, action, arguments):
        self.action = action
        self.arguments = arguments
        super().__init__(f"SQLite observer denied action {action}: {arguments}")


def typed(value):
    if value is None:
        return ["null", None]
    if type(value) is int:
        return ["integer", str(value)]
    if type(value) is float:
        return ["real", value.hex()]
    if type(value) is bytes:
        return ["blob", value.hex()]
    if type(value) is str:
        try:
            value.encode()
        except UnicodeEncodeError:
            return ["text-bytes", value.encode(errors="surrogateescape").hex()]
        return ["text", value]
    raise TypeError(type(value))


class Database:
    policy_digest = OBSERVER_CONTRACT
    text_observation_digest = TEXT_OBSERVATION_CONTRACT

    def __init__(self, library, filename=b":memory:"):
        contract = Path(__file__).with_name("sqlite-text-observation-v1.json")
        if hashlib.sha256(contract.read_bytes()).hexdigest() != TEXT_OBSERVATION_CONTRACT:
            raise ValueError("SQLite text observation differs from its recorded revision")
        self.library = library
        self.lib = c.CDLL(str(library))
        self.allowed_actions = frozenset(observer_policy()["allowedActions"].values())
        self.digest = hashlib.sha256(Path(library).read_bytes()).hexdigest()
        p, i, s, q, f = c.c_void_p, c.c_int, c.c_char_p, c.c_int64, c.c_double
        for name, result, args in [
            ("libversion", s, []), ("sourceid", s, []),
            ("open", i, [s, c.POINTER(p)]), ("close", i, [p]),
            ("errmsg", s, [p]), ("errcode", i, [p]), ("exec", i, [p, s, p, p, p]),
            ("prepare_v2", i, [p, s, i, c.POINTER(p), c.POINTER(s)]),
            ("finalize", i, [p]), ("step", i, [p]),
            ("bind_parameter_count", i, [p]), ("bind_parameter_name", s, [p, i]),
            ("bind_null", i, [p, i]), ("bind_int64", i, [p, i, q]),
            ("bind_double", i, [p, i, f]), ("bind_text", i, [p, i, s, i, p]),
            ("column_count", i, [p]), ("column_name", s, [p, i]),
            ("column_type", i, [p, i]), ("column_int64", q, [p, i]),
            ("column_double", f, [p, i]), ("column_text", p, [p, i]),
            ("column_blob", p, [p, i]), ("column_bytes", i, [p, i]),
            ("stmt_readonly", i, [p]), ("total_changes", i, [p]), ("changes", i, [p]),
            ("get_autocommit", i, [p]),
            ("set_authorizer", i, [p, p, p]),
        ]:
            function = getattr(self.lib, "sqlite3_" + name)
            function.argtypes, function.restype = args, result
            setattr(self, name, function)
        self.db = p()
        self.check(self.open(filename, c.byref(self.db)))
        self.version = self.libversion().decode()
        self.source = self.sourceid().decode()
        self.authorizer = AUTHORIZER(self.authorize)
        self.denied = None

    def authorize(self, _, action, first, second, schema, origin):
        if action in self.allowed_actions and schema in (None, b"main", b"temp"):
            return 0
        if self.denied is None:
            self.denied = (action, [value.decode(errors="replace") if value is not None else None
                                    for value in (first, second, schema, origin)])
        return 1

    def check(self, code, expected=0):
        if code != expected:
            raise RuntimeError(f"SQLite {code}: {self.errmsg(self.db).decode()}")

    def execute(self, sql):
        self.check(self.exec(self.db, sql.encode(), None, None, None))

    def value(self, stmt, column):
        storage = self.column_type(stmt, column)
        if storage == 1:
            return self.column_int64(stmt, column)
        if storage == 2:
            return self.column_double(stmt, column)
        if storage == 3:
            data = self.column_text(stmt, column)
            if not data:
                self.check(self.errcode(self.db))
                raise RuntimeError("SQLite TEXT result bytes are unavailable")
            return c.string_at(data, self.column_bytes(stmt, column)).decode(errors="surrogateescape")
        if storage == 4:
            data = self.column_blob(stmt, column)
            return c.string_at(data, self.column_bytes(stmt, column))
        if storage == 5:
            return None
        raise RuntimeError(f"Unknown SQLite storage class {storage}")

    def observe(self, sql, bindings, preserve_order=False, capture_evaluation_error=False):
        self.denied = None
        self.check(self.set_authorizer(self.db, self.authorizer, None))
        try:
            return self._observe(sql, bindings, preserve_order, capture_evaluation_error)
        finally:
            self.check(self.set_authorizer(self.db, None, None))
            if self.denied is not None:
                raise ObserverAuthorizationError(*self.denied)

    def metadata(self, name, *, schema=None, table=None):
        if name == "table_xinfo" and schema in ("main", "temp") and table is not None:
            identifier = table.replace('"', '""')
            sql = f'PRAGMA {schema}.table_xinfo("{identifier}")'
        elif name in ("compile_options", "foreign_keys", "journal_mode") and schema is None and table is None:
            sql = f"PRAGMA {name}"
        else:
            raise ValueError("Unknown SQLite fixture metadata query")
        return self._observe(sql, {}, preserve_order=name == "table_xinfo")["rows"]

    def _observe(self, sql, bindings, preserve_order=False, capture_evaluation_error=False):
        stmt, tail = c.c_void_p(), c.c_char_p()
        encoded = sql.encode()
        self.check(self.prepare_v2(self.db, encoded, len(encoded) + 1, c.byref(stmt), c.byref(tail)))
        changes = self.total_changes(self.db)
        try:
            if not stmt or tail.value.strip():
                raise RuntimeError("Expected exactly one prepared statement")
            count = self.bind_parameter_count(stmt)
            parameters = {}
            for slot in range(1, count + 1):
                name = self.bind_parameter_name(stmt, slot)
                if name is not None:
                    parameters[str(slot)] = name.decode()
            for key, value in bindings.items():
                slot = int(key)
                if value is None:
                    self.check(self.bind_null(stmt, slot))
                elif type(value) is int:
                    self.check(self.bind_int64(stmt, slot, value))
                elif type(value) is float:
                    self.check(self.bind_double(stmt, slot, value))
                else:
                    data = value.encode()
                    self.check(self.bind_text(stmt, slot, data, len(data), c.c_void_p(-1)))
            width = self.column_count(stmt)
            readonly = bool(self.stmt_readonly(stmt))
            rows = []
            while (code := self.step(stmt)) == 100:
                rows.append([typed(self.value(stmt, index)) for index in range(width)])
            evaluation_error = None
            if capture_evaluation_error:
                if code != 101:
                    evaluation_error = {"code": code, "message": self.errmsg(self.db).decode()}
            else:
                self.check(code, 101)
            return {
                "names": [self.column_name(stmt, index).decode() for index in range(width)],
                "parameters": parameters, "parameterCount": count,
                "rows": rows if preserve_order else sorted(rows), "readOnly": readonly,
                "changes": self.total_changes(self.db) - changes,
                **({"evaluationError": evaluation_error} if capture_evaluation_error else {}),
            }
        finally:
            self.finalize(stmt)
