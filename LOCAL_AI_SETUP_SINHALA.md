# RPTA Local Generative AI – API නැති Version එක

මේ version එක **OpenAI API භාවිතා කරන්නේ නැහැ**. API key හෝ API credits අවශ්‍ය නැහැ.

## 1. Ollama install කරන්න

Windows සඳහා Ollama install කරගන්න:
https://ollama.com/download/windows

Install කළාට පස්සේ Command Prompt (CMD) එකක් open කරන්න.

## 2. AI model එක download කරන්න

CMD එකේ:

```bat
ollama pull qwen2.5:3b
```

මෙය එක වරක් download කරන්න. Internet එක අවශ්‍ය වන්නේ model එක download කරන වෙලාවේ විතරයි.

Laptop එකේ RAM අඩු නම්:

```bat
ollama pull qwen2.5:1.5b
```

ඊට පස්සේ `local_ai_config.php` එකේ model එකත් ඒ නමට වෙනස් කරන්න.

## 3. Ollama run කරන්න

CMD:

```bat
ollama run qwen2.5:3b
```

මෙය run වෙන අතරතුර website එකේ chatbot එක භාවිතා කරන්න.

## 4. XAMPP

Apache + MySQL Start කරන්න.

Website:

```text
http://localhost/rpta_portal/
```

## 5. Local AI test කරන්න

Browser:

```text
http://localhost/rpta_portal/local_ai_test.php
```

`Local AI is working` කියලා පෙන්වුවොත් connection එක හරි.

## වැදගත්

- OpenAI API key ❌
- OpenAI API payment ❌
- API credits ❌
- ChatGPT subscription ❌
- Internet API call ❌
- Local model download එකට internet එක අවශ්‍යයි ✅
- Model download කළ පසු chatbot inference එක local computer එකේ සිදුවේ ✅

## Project report එකේ නිවැරදිව කියන්න

**“A locally hosted generative AI chatbot using an open-source large language model (Qwen) and an RPTA-specific FAQ knowledge base.”**

මෙය OpenAI/ChatGPT API chatbot එකක් ලෙස report කරන්න එපා.
