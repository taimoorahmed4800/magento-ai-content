# Taimoor_AiContent: AI Product Content Generator for Magento 2

Generates product **description**, **short description**, **meta title** and
**meta description** with an AI (LLM) API.

Tested target: Magento Open Source 2.4.7-p8, Luma theme, PHP 8.2 / 8.3.
(This module only works in the admin, so it does not depend on the storefront theme.)

## Features

- Settings page: Stores > Configuration > Taimoor > AI Content Generator
  (API URL, encrypted API key, model, content language, brand tone, overwrite on/off)
- "Generate AI Content" button on the product edit page
- "Generate AI Content" mass action in the product grid (max 10 products per run)
- CLI command with dry-run: `bin/magento taimoor:ai-content:generate SKU --dry-run`
- Works with any OpenAI-compatible API (Gemini, Groq, OpenRouter, Ollama)

## Install

1. Copy the `app` folder of this zip into your Magento root.
   You should have `app/code/Taimoor/AiContent/registration.php`.
2. Run:

       bin/magento module:enable Taimoor_AiContent
       bin/magento setup:upgrade
       bin/magento setup:di:compile      (only in production mode)
       bin/magento cache:flush

3. Go to Stores > Configuration > Taimoor > AI Content Generator:
   - Enabled: Yes
   - API Base URL: https://generativelanguage.googleapis.com/v1beta/openai
   - API Key: your key (stored encrypted)
   - Model: gemini-3.5-flash-lite (or any model from your provider)
   - Content Language: English (or Arabic, Urdu...)
   - Save Config, then `bin/magento cache:flush`

## How to test (safe order)

1. Dry run in the terminal (nothing is saved):

       bin/magento taimoor:ai-content:generate YOUR-SKU --dry-run

2. If the output looks good, run it without `--dry-run`, or open the product in
   the admin and click "Generate AI Content".
3. Select 2 or 3 products in Catalog > Products > Actions > Generate AI Content.

## How it works

1. `ContentGenerator` collects the product facts (name, SKU, price, categories,
   attributes visible on the storefront, existing description).
2. It sends them to the AI with strict rules: use only these facts, return only JSON.
3. `LlmClient` calls the API (3 attempts for rate-limit / busy errors).
4. The answer is cleaned: only safe HTML tags (p, ul, ol, li, strong, em, br),
   no attributes, meta title max 60 and meta description max 155 characters.
5. Only the 4 attributes are saved (`saveAttribute`), in the default scope.
   By default fields that already have content are NOT replaced.

## Good to know

- Always review the AI text before publishing. AI can still make mistakes.
- Values are saved in the default scope (All Store Views). If a store view has
  its own value for an attribute, that value still wins on that store view.
- The edit-page button reloads the page: save your product changes first.
- If the description editor uses Page Builder, the new text appears as HTML content.
- The mass action runs one AI call per product during the request, so it is
  limited to 10 products. For thousands of products, move the work into
  Magento's message queue (see ideas below).
- The API key is stored encrypted and is never written to the logs.
- A free AI tier has rate limits. Do not send confidential data to it.

## Ideas to extend it

- Process products in the background with a message queue consumer.
- Add a preview modal: show the AI text and let the admin approve it.
- Per store view languages (Arabic and English) with store view scope saving.
- A "last generated" attribute and a log table in the database.
- Unit tests for `ContentGenerator` with a mocked `LlmClient`.
