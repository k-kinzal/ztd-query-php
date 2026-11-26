"""Match trusted wire responses and raw evidence to reproduced requests."""

import itertools
import json
from collections import deque

from run import TOOL, digest, require

REPLAY_CONTRACT = "2daee7efd5324c9c84de0cb1b3bc8a61c748a4857d30dbbb94a6f7e7bbd76f1b"


def load_replay_contract():
    path = TOOL / "evidence-replay-v1.json"
    require(digest(path) == REPLAY_CONTRACT, "replay-contract-mismatch", str(path))
    return json.loads(path.read_text())


def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(",", ":"), allow_nan=False)


def events(path):
    require(path.is_file(), "missing-evidence-transcript", str(path))
    with path.open() as stream:
        for line in stream:
            event = json.loads(line)
            require(isinstance(event, dict) and type(event.get("id")) is str and type(event.get("event")) is str,
                    "invalid-evidence-event", str(path))
            yield event


class Transcript:
    def __init__(self, path, environment):
        self.environment = environment
        self.frames = self.load(path)
        self.consumed = 0

    @staticmethod
    def load(path):
        pending = {}
        responses = {}
        for event in events(path):
            key, step = event["id"], event["event"]
            if step == "attempted":
                require(key not in pending and type(event.get("input")) is dict, "duplicate-or-invalid-evidence-request", key)
                pending[key] = event["input"]
            if step in ("constructed", "rejected", "snapshot"):
                require(key in pending and key not in responses and type(event.get("response")) is dict,
                        "duplicate-or-missing-evidence-response", key)
                responses[key] = event["response"]
        require(bool(pending), "empty-evidence-transcript", str(path))
        require(pending.keys() == responses.keys(), "incomplete-evidence-transcript", sorted(pending.keys() - responses.keys()))
        return deque((key, item, responses[key]) for key, item in pending.items())

    def __enter__(self):
        return self

    def __exit__(self, kind, *_):
        if kind is None:
            require(not self.frames, "unconsumed-evidence-requests", len(self.frames))

    def request(self, request):
        if isinstance(request, list):
            return [self.request(item) for item in request]
        require(bool(self.frames), "missing-evidence-request", self.consumed)
        key, recorded, response = self.frames.popleft()
        require(canonical(recorded) == canonical(request), "evidence-request-mismatch", key)
        self.consumed += 1
        return response


def require_reproduced(recorded, reproduced):
    for index, values in enumerate(itertools.zip_longest(events(recorded), events(reproduced)), 1):
        before, after = values
        require(before is not None and after is not None and canonical(before) == canonical(after),
                "evidence-ledger-mismatch", {
                    "line": index,
                    "recorded": [before["id"], before["event"]] if before else None,
                    "reproduced": [after["id"], after["event"]] if after else None,
                })
