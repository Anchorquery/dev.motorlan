@echo off
cd /d "%~dp0"
node split_entradas.cjs > output.log 2>&1
echo "Done"
