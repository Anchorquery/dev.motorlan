@echo off
cd /d "%~dp0"
echo Running Entradas split...
node split_entradas.cjs
echo.
echo Running Categorias split (if file exists)...
if exist "Categorias-Export-2026-February-08-0029.csv" (
    node split_categories.cjs
) else (
    echo Categorias export file not found.
)
echo.
echo Running Etiquetas split (if file exists)...
if exist "Etiquetas-Export-2026-February-08-0032.csv" (
    node split_csv.cjs
) else (
    echo Etiquetas export file not found.
)
echo.
echo All done.
pause
