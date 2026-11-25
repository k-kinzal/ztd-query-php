"""Replay complete MySQL truncation foreign-key evidence."""

from combine_server_join_evidence import main
import run_server_mysql_truncate_foreign_keys

if __name__ == "__main__":
    main(run_server_mysql_truncate_foreign_keys)
