# Pigment Dev - AI Review Auto-Reply for WooCommerce

Automatically reply to WooCommerce product reviews using AI.
The plugin learns from your product descriptions, details, and previous replies to generate new responses in the same tone and style.

---

## ✨ Features

- 🤖 **AI-powered replies** to product reviews (based on product info + site knowledge).
- 🎯 **Tone mirroring** — adapts to your previous replies and keeps the same style.
- 🛒 **WooCommerce integration** — only triggers on product reviews.
- ⚙️ **Flexible settings**:
  - Choose AI model and provider endpoint (works with OpenAI, local APIs, etc.).
  - Adjust temperature, token limits, and delay before posting.
  - Option to post replies as a specific WordPress user.
- 🧪 **Test mode** — send sample reviews to the AI and preview replies before going live.
- 🛡️ **Mock mode** — no external API calls, uses simple local templates.

---


## ⬇️ Download

You can download the latest zipped release from the [GitHub Releases page](https://github.com/pigment-dev/wc-ai-review/releases/latest).

**This plugin is completely free, open source, and ad-free.**

---

## 🔧 Installation

1. Download or clone this repository into your WordPress `wp-content/plugins` directory:
   ```bash
   git clone https://github.com/pigment-dev/wc-ai-review.git
   ```
2. Activate **Pigment Dev - AI Review Auto-Reply for WooCommerce** from the **WordPress Admin → Plugins** page.
3. Go to **AI Review Reply** in the admin menu to configure settings.

---

## ⚙️ Configuration

In the **Admin → AI Review Reply** settings you can configure:

- **Enable auto-replies** (turn the plugin on/off).
- **Provider endpoint URL** (e.g., `https://api.openai.com/v1/chat/completions`).
- **API key** (stored securely).
- **API key header & format** (works with providers that require custom headers).
- **Model name** (e.g., `gpt-4o-mini`, `llama3`, `claude`).
- **Temperature** and **Max tokens** for tuning responses.
- **Examples** — how many previous replies to learn tone from.
- **Guidelines** — add your brand voice or policy notes.
- **Reply as user ID** — select which WordPress user should post replies.
- **Mock mode** — generate replies locally without AI calls.
- **Reply delay** — wait a few seconds/minutes before posting.

---

## 🌐 Supported AI Providers & Models

Your plugin can connect to any AI provider with an OpenAI-compatible API.
Here are some popular options:

| Provider      | Example Models                              | Endpoint Example |
|---------------|---------------------------------------------|------------------|
| **OpenAI**    | GPT-4o, GPT-4o-mini, GPT-3.5 Turbo          | `https://api.openai.com/v1/chat/completions` |
| **Anthropic** | Claude 3.5 Sonnet, Claude 3 Haiku           | `https://api.anthropic.com/v1/messages` |
| **Google**    | Gemini 1.5 Pro, Gemini 1.5 Flash            | `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent` |
| **DeepSeek** (via OpenRouter) | DeepSeek-R1 (free/pro), DeepSeek-Coder | `https://openrouter.ai/api/v1/chat/completions` |
| **Mistral AI**| Mistral 7B, Mixtral 8x7B, Codestral         | `https://api.mistral.ai/v1/chat/completions` |
| **Meta (LLaMA)** | LLaMA 3 (8B, 70B)                        | Hugging Face: `https://api-inference.huggingface.co/models/...` <br> or local via Ollama |
| **Cohere**    | Command R+, Command R                       | `https://api.cohere.ai/v1/chat` |
| **Hugging Face** | Phi-2, TinyLLaMA, Falcon, StableLM       | `https://api-inference.huggingface.co/models/{model}` |
| **Ollama (local)** | LLaMA 3, Mistral, Phi-2, TinyLLaMA     | `http://localhost:11434/api/generate` |

---

👉 Notes:
- Use **OpenRouter** if you want access to multiple providers/models through one API.
- For local testing, **Ollama** is the easiest option (Mac/Linux/Windows).
- Hugging Face offers free tiers for many models, but with rate limits.

---

## 🚀 Example: DeepSeek R1 (Free via OpenRouter)

You can quickly test the plugin using the free DeepSeek R1 model on OpenRouter.

### Settings
- **Provider URL:** `https://openrouter.ai/api/v1/chat/completions`
- **Model:** `deepseek/deepseek-r1:free`
- **API Key:**
  1. Create a free account on OpenRouter.
  2. Go to **Dashboard → API Keys** and generate a key.
  3. Paste the key in the plugin settings.
- **Header example:** `Authorization: Bearer YOUR_API_KEY`

### ✅ Result
Once configured, the plugin will automatically reply to WooCommerce product reviews using **DeepSeek R1 (free)**.

---

## 🚀 Usage

- When a **customer leaves a review**, the plugin automatically generates a reply:
  - If the review is **positive** → expresses gratitude.
  - If the review is **negative** → apologizes and offers next steps.
  - Replies in the **same language** as the review.
- Replies are posted as **comment replies** under the customer’s review.

---

## 🧪 Testing

From the **Admin → AI Review Reply** page:
- Enter a **Product ID** and a **sample review**.
- Run the **Test Prompt** button.
- See the generated AI response before enabling auto-replies.

---

## ⚠️ Disclaimer

This plugin connects to an external AI provider of your choice.
- Ensure compliance with provider’s terms of service.
- Replies are **automatically generated** — always review critical comments manually.
- Use **Mock mode** if you don’t want to send external requests during testing.

---

## 🛡️ Warranty Disclaimer

This plugin is provided **"as is"** without any warranty of any kind, either expressed or implied. The authors and contributors are not liable for any damages or issues arising from the use of this plugin. Use at your own risk.

---

## 📄 License

This project is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

---

## 👨‍💻 Author

Developed with 💙 by [Pigment Dev](https://pimgent.dev/) for **Open-source**

- GitHub: [https://github.com/pigment-dev](https://github.com/pigment-dev)
- Instagram: [https://www.instagram.com/pigment.dev](https://www.instagram.com/pigment.dev)
- LinkedIn: [https://www.linkedin.com/company/pigment-dev/](https://www.linkedin.com/company/pigment-dev/)
- X (Twitter): [https://x.com/pigment_develop](https://x.com/pigment_develop)

---
