"""Replay every pinned native MySQL VARCHAR VALUES report."""

import run_server_mysql_varchar_insert
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_varchar_insert)
