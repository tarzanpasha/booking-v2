#!/bin/bash
set -euo pipefail

OUTPUT_FILE="project_contents.txt"
: > "$OUTPUT_FILE"

# Папки, которые сканируем
SCAN_DIRS=(app database routes)

# Пути (относительно корня проекта), которые исключаем
EXCLUDE_PATHS=(
  "app/Console"
  "app/Data"
  "app/Events"
  "app/Listeners"
  "app/Providers"
  "app/Http/Middleware"
)

# Собираем выражение для prune из массива EXCLUDE_PATHS
PRUNE_EXPR=()
for p in "${EXCLUDE_PATHS[@]}"; do
  # исключаем и сам каталог, и всё внутри него
  PRUNE_EXPR+=( -path "./$p" -o -path "./$p/*" -o )
done
# убрать последний лишний -o
if ((${#PRUNE_EXPR[@]})); then
  unset 'PRUNE_EXPR[${#PRUNE_EXPR[@]}-1]'
fi

for dir in "${SCAN_DIRS[@]}"; do
  [[ -d "$dir" ]] || continue

  if ((${#PRUNE_EXPR[@]})); then
    find "./$dir" \
      \( "${PRUNE_EXPR[@]}" \) -prune -o \
      -type f -print0 2>/dev/null |
    while IFS= read -r -d '' file; do
      [[ -r "$file" && -s "$file" ]] || continue
      if file "$file" | grep -q "text"; then
        echo "=== ${file#./} ===" >> "$OUTPUT_FILE"
        cat "$file" >> "$OUTPUT_FILE"
        printf "\n\n" >> "$OUTPUT_FILE"
      fi
    done
  else
    find "./$dir" -type f -print0 2>/dev/null |
    while IFS= read -r -d '' file; do
      [[ -r "$file" && -s "$file" ]] || continue
      if file "$file" | grep -q "text"; then
        echo "=== ${file#./} ===" >> "$OUTPUT_FILE"
        cat "$file" >> "$OUTPUT_FILE"
        printf "\n\n" >> "$OUTPUT_FILE"
      fi
    done
  fi
done

echo "Готово! Файл создан: $OUTPUT_FILE"
