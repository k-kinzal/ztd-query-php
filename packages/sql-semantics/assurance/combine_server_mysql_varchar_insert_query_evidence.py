"""Replay the native VARCHAR query write transcripts and fault witnesses."""

from combine_server_join_evidence import main
import run_server_mysql_varchar_insert_query


if __name__ == "__main__":
    main(run_server_mysql_varchar_insert_query)
