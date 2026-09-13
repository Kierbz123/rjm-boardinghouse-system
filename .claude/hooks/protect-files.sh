#!/bin/bash
# .claude/hooks/protect-files.sh
# Blocks Claude from editing sensitive files. Runs before every Edit/Write.
# Exit 2 = block. Deterministic — not advisory like a CLAUDE.md instruction.

INPUT=$(cat)
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

# Normalize Windows backslash separators so the patterns below match
FILE_PATH="${FILE_PATH//\\//}"

PROTECTED_PATTERNS=(".env" ".git/" "config/database.php" "id_rsa" ".pem")

for pattern in "${PROTECTED_PATTERNS[@]}"; do
  if [[ "$FILE_PATH" == *"$pattern"* ]]; then
    echo "Blocked: $FILE_PATH matches protected pattern '$pattern'. Edit .env.example instead, or ask the user to change this file directly." >&2
    exit 2
  fi
done

exit 0
