"""Replay every pinned native MySQL VARCHAR assignment report."""

import run_server_mysql_varchar_assignment
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_varchar_assignment)
