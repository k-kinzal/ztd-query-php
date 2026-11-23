"""Replay every pinned native MySQL VARCHAR COUNT report."""

import run_server_mysql_varchar_count
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_varchar_count)
