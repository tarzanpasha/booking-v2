#!/bin/bash

# Переходим в корневую директорию проекта
cd "$(dirname "$0")"

# Базовый URL для сырых файлов
BASE_URL="https://raw.githubusercontent.com/tarzanpasha/booking-v2/refs/heads/master"

# Создаем файл с полными URL
find . -type f \
    ! -path "./Vendor/*" \
    ! -path "./vendor/*" \
    ! -path "./Docker/*" \
    ! -path "./docker/*" \
    ! -path "./.git/*" \
    ! -path "./.idea/*" \
    -print0 | while IFS= read -r -d '' file; do
    # Удаляем ./ в начале пути и добавляем URL
    relative_path="${file#./}"
    echo "${BASE_URL}/${relative_path}"
done > project_files.txt

echo "Файл project_files.txt создан"
echo "Найдено файлов: $(wc -l < project_files.txt)"
