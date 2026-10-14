# RPTA Local Generative AI – No API Payment

This version does NOT use OpenAI API. No OpenAI API key or API credits are required.

1. Install Ollama for Windows:
   https://ollama.com/download/windows

2. Open Command Prompt and download the model once:
   ollama pull qwen2.5:3b

3. Start the model:
   ollama run qwen2.5:3b

4. Start Apache + MySQL in XAMPP.

5. Open:
   http://localhost/rpta_portal/

6. Test local AI:
   http://localhost/rpta_portal/local_ai_test.php

If the laptop has low RAM, you can use qwen2.5:1.5b and change LOCAL_AI_MODEL in local_ai_config.php.

The website sends questions to Ollama at:
http://127.0.0.1:11434/api/generate

No paid API is used. After the model is downloaded, generation runs locally on the computer.
