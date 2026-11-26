"""Replay native MySQL VARCHAR presence observations on every pinned release."""

import run_server_mysql_varchar_presence
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_varchar_presence)
