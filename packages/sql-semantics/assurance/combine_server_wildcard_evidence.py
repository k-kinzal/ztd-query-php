"""Replay native wildcard evidence over all pinned release profiles."""

import run_server_wildcard
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_wildcard)
