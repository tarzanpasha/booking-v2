#!/bin/bash

OUTPUT_FILE="project_contents.txt"

# Очищаем выходной файл
> "$OUTPUT_FILE"

# Обрабатываем файлы в указанных папках
for folder in app database routes; do
    if [ -d "$folder" ]; then
        find "$folder" -type f -name "*" 2>/dev/null | while read -r file; do
            if [ -r "$file" ] && [ -s "$file" ]; then
                # Проверяем, что файл текстовый (опционально)
                if file "$file" | grep -q "text"; then
                    echo "=== $file ===" >> "$OUTPUT_FILE"
                    cat "$file" >> "$OUTPUT_FILE"
                    echo -e "\n\n" >> "$OUTPUT_FILE"
                fi
            fi
        done
    fi
done

echo "Готово! Файл создан: $OUTPUT_FILE"
