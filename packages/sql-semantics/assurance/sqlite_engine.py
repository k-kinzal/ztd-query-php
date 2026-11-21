"""SQLite observations through the C interface of the specified engine build."""

import ctypes as c
import hashlib
from pathlib import Path


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
        return ["text", value]
    raise TypeError(type(value))


class Database:
    def __init__(self, library):
        self.lib = c.CDLL(str(library))
        self.digest = hashlib.sha256(Path(library).read_bytes()).hexdigest()
        p, i, s, q, f = c.c_void_p, c.c_int, c.c_char_p, c.c_int64, c.c_double
        for name, result, args in [
            ("libversion", s, []), ("sourceid", s, []),
            ("open", i, [s, c.POINTER(p)]), ("close", i, [p]),
            ("errmsg", s, [p]), ("exec", i, [p, s, p, p, p]),
            ("prepare_v2", i, [p, s, i, c.POINTER(p), c.POINTER(s)]),
            ("finalize", i, [p]), ("step", i, [p]),
            ("bind_parameter_count", i, [p]), ("bind_parameter_name", s, [p, i]),
            ("bind_null", i, [p, i]), ("bind_int64", i, [p, i, q]),
            ("bind_double", i, [p, i, f]), ("bind_text", i, [p, i, s, i, p]),
            ("column_count", i, [p]), ("column_name", s, [p, i]),
            ("column_type", i, [p, i]), ("column_int64", q, [p, i]),
            ("column_double", f, [p, i]), ("column_text", p, [p, i]),
            ("column_blob", p, [p, i]), ("column_bytes", i, [p, i]),
            ("stmt_readonly", i, [p]), ("total_changes", i, [p]),
        ]:
            function = getattr(self.lib, "sqlite3_" + name)
            function.argtypes, function.restype = args, result
            setattr(self, name, function)
        self.db = p()
        self.check(self.open(b":memory:", c.byref(self.db)))
        self.version = self.libversion().decode()
        self.source = self.sourceid().decode()

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
            return c.string_at(data, self.column_bytes(stmt, column)).decode()
        if storage == 4:
            data = self.column_blob(stmt, column)
            return c.string_at(data, self.column_bytes(stmt, column))
        if storage == 5:
            return None
        raise RuntimeError(f"Unknown SQLite storage class {storage}")

    def observe(self, sql, bindings):
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
            self.check(code, 101)
            return {
                "names": [self.column_name(stmt, index).decode() for index in range(width)],
                "parameters": parameters, "parameterCount": count,
                "rows": sorted(rows), "readOnly": readonly,
                "changes": self.total_changes(self.db) - changes,
            }
        finally:
            self.finalize(stmt)
