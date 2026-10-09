@echo off
cd /d "%~dp0"
pip install pyinstaller Pillow
pyinstaller --onefile --windowed --name "WebP Optimizer" webp_optimizer.py
echo.
echo Done. Your app is in the dist folder: dist\WebP Optimizer.exe
pause
