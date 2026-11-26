"""Replay native MySQL VARCHAR-filtered mutation evidence."""

import run_server_mysql_varchar_effects
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_varchar_effects)
