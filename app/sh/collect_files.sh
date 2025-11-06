#!/bin/bash

# Переменные
OUTPUT_FILE="project.txt"
TARGET_DIRS=("app" "routes")

# Проверяем существование целевых папок
for dir in "${TARGET_DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        echo "Ошибка: Папка '$dir' не найдена!"
        exit 1
    fi
done

# Создаем или очищаем выходной файл
> "$OUTPUT_FILE"

# Функция для обработки файлов
process_directory() {
    local dir="$1"
    echo "Обрабатываем папку: $dir"

    # Рекурсивно ищем все файлы
    find "$dir" -type f | while read -r filepath; do
        echo "Добавляем файл: $filepath"

        # Добавляем разделитель с полным путем к файлу
        echo "##$filepath##" >> "$OUTPUT_FILE"
        echo "========================================" >> "$OUTPUT_FILE"

        # Добавляем содержимое файла
        if [ -r "$filepath" ]; then
            cat "$filepath" >> "$OUTPUT_FILE"
        else
            echo "[Невозможно прочитать файл]" >> "$OUTPUT_FILE"
        fi

        # Добавляем разделитель между файлами
        echo -e "\n\n*** КОНЕЦ ФАЙЛА: $filepath ***\n\n" >> "$OUTPUT_FILE"
    done
}

# Основной процесс
echo "Начинаем сборку project.txt..."
echo "Дата создания: $(date)" >> "$OUTPUT_FILE"
echo "Проект: $(pwd)" >> "$OUTPUT_FILE"
echo "========================================" >> "$OUTPUT_FILE"

# Обрабатываем каждую целевую папку
for dir in "${TARGET_DIRS[@]}"; do
    process_directory "$dir"
done

echo "Готово! Файл '$OUTPUT_FILE' создан."
echo "Общее количество обработанных файлов: $(grep -c "##.*##" "$OUTPUT_FILE")"
