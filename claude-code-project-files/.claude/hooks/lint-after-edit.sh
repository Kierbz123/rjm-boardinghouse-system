#!/bin/bash
# .claude/hooks/lint-after-edit.sh
# Runs a syntax check after every Edit/Write and feeds failures back to Claude
# as a concrete pass/fail signal, per "give Claude a way to verify its work."

INPUT=$(cat)
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

if [[ -z "$FILE_PATH" ]]; then
  exit 0
fi

case "$FILE_PATH" in
  *.php)
    if command -v php >/dev/null 2>&1; then
      if ! OUTPUT=$(php -l "$FILE_PATH" 2>&1); then
        echo "PHP syntax error in $FILE_PATH:" >&2
        echo "$OUTPUT" >&2
        exit 2
      fi
    fi
    ;;
  scoring_service/*.py|*.py)
    if command -v python3 >/dev/null 2>&1; then
      if ! OUTPUT=$(python3 -m py_compile "$FILE_PATH" 2>&1); then
        echo "Python syntax error in $FILE_PATH:" >&2
        echo "$OUTPUT" >&2
        exit 2
      fi
    fi
    ;;
esac

exit 0
