@echo off
title RPTA Local Generative AI
echo ==========================================
echo RPTA Local Generative AI
echo ==========================================
where ollama >nul 2>nul
if errorlevel 1 (
  echo.
  echo Ollama is not installed or is not in PATH.
  echo Install Ollama from: https://ollama.com/download/windows
  echo.
  pause
  exit /b 1
)
echo.
echo Checking/downloading the Qwen model...
ollama pull qwen2.5:3b
echo.
echo Starting local AI model...
echo Keep this window open while using the chatbot.
echo.
ollama run qwen2.5:3b
pause
