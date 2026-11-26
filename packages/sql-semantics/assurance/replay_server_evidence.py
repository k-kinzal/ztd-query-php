"""Recompute the server oracle and check logged observations and requests."""

import json
import tempfile
from collections import deque
from pathlib import Path
from types import SimpleNamespace

from evidence_transcript import Transcript, canonical, events, require_reproduced
from run import require
from run_server import run_dialect
from server_cases import fixtures


class RecordedObserver:
    def __init__(self, dialect, path, environment):
        self.environment = environment
        self.frames = deque([({"fixture": fixtures(dialect)}, {"status": "fixture"})])
        inputs, outputs = {}, {}
        for event in events(path):
            key, step = event["id"], event["event"]
            if step == "attempted":
                inputs[key] = event["input"]
            elif step == "constructed":
                outputs[key] = event["response"]["sql"]
            elif step == "compared":
                if "sql" in inputs[key]:
                    require(type(event.get("original")) is dict, "server-original-observation-missing", key)
                    self.frames.append(({"sql": inputs[key]["sql"]}, event["original"]))
                self.frames.append(({"sql": outputs[key]}, event["observation"]))

    def __enter__(self):
        return self

    def __exit__(self, kind, *_):
        if kind is None:
            require(not self.frames, "unconsumed-server-observations", len(self.frames))

    def request(self, request):
        if isinstance(request, list):
            return [self.request(item) for item in request]
        require(bool(self.frames), "missing-server-observation", request)
        recorded, observation = self.frames.popleft()
        require(recorded == request, "server-observation-request-mismatch", request)
        return observation


def replay(dialect, path, result, settings):
    worker = Transcript(path, result["php"])
    observer = RecordedObserver(dialect, path, result["engine"])

    def worker_factory(*_, **__):
        return worker

    def observer_factory(*_):
        return observer

    with tempfile.TemporaryDirectory(prefix="semantics-ir-server-replay-") as temporary:
        output = Path(temporary) / path.name
        reproduced = run_dialect(dialect, result["release"], SimpleNamespace(), settings, output, worker_factory, observer_factory)
        require_reproduced(path, output)
    expected = {key: value for key, value in result.items() if key != "shape"}
    require(canonical(json.loads(json.dumps(reproduced))) == canonical(expected), "server-summary-mismatch", str(path))
    return worker.consumed
