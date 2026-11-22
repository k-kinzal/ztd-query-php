"""Replay native grouped observations on every pinned release."""

import run_server_grouped
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_grouped)
