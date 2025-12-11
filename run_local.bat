@echo off
echo ==========================================
echo  LITODA Face Recognition System - Local Setup
echo ==========================================

REM Check if Python is installed
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: Python is not installed or not in PATH.
    echo Please install Python 3.10 or higher from python.org
    pause
    exit /b
)

REM Create Virtual Environment if it doesn't exist
if not exist "venv" (
    echo.
    echo Creating virtual environment...
    python -m venv venv
)

REM Activate Virtual Environment
echo.
echo Activating virtual environment...
call venv\Scripts\activate

REM Upgrade pip
echo.
echo Upgrading pip...
python -m pip install --upgrade pip

REM Install dependencies from requirements.txt
echo.
echo Installing dependencies from requirements.txt...
pip install -r requirements.txt

REM Ensure DeepFace and ArcFace are installed
echo.
echo ==========================================
echo  Installing DeepFace and ArcFace Model...
echo ==========================================
pip install deepface --upgrade
pip install tf-keras --upgrade

REM Download ArcFace model (will auto-download on first run)
echo.
echo DeepFace will automatically download ArcFace model on first use.
echo This may take a few minutes...

echo.
echo ==========================================
echo  Verifying Installation...
echo ==========================================
python -c "import deepface; print('✓ DeepFace installed successfully')"
python -c "import cv2; print('✓ OpenCV installed successfully')"
python -c "import flask; print('✓ Flask installed successfully')"
python -c "import mysql.connector; print('✓ MySQL Connector installed successfully')"

echo.
echo ==========================================
echo  Setup Complete!
echo  Starting Python Server...
echo  Make sure XAMPP MySQL is running!
echo ==========================================
echo.
echo Server will start in 3 seconds...
timeout /t 3 /nobreak >nul

python face_recognition_system.py

pause
