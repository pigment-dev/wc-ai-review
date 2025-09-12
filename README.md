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

## 🛠 Development Notes

- Written in **PHP OOP style**, following WordPress coding standards.
- Uses **wp_remote_post()** for AI API calls.
- Compatible with **WooCommerce product review system**.
- Supports **WordPress Cron** for delayed posting.

---

## ⚠️ Disclaimer

This plugin connects to an external AI provider of your choice.
- Ensure compliance with provider’s terms of service.
- Replies are **automatically generated** — always review critical comments manually.
- Use **Mock mode** if you don’t want to send external requests during testing.

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
