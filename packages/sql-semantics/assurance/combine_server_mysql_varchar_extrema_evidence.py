"""Replay MySQL VARCHAR extrema observations and renderer fault witnesses."""

import run_server_mysql_varchar_extrema
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_varchar_extrema)
