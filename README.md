# yii2-openai-responses

Easily integrate AI features into any Yii2 application. This library provides a drop‑in component to interact with OpenAI's Responses API.

Wrapper for the [openai-php/client](https://github.com/openai-php/client) focused on the Responses API.

## Installation

```bash
composer require eseperio/yii2-openai-responses
```

## Configuration

Register the component in the application configuration:

```php
return [
    'components' => [
        'openai' => [
            'class' => eseperio\openai\responses\OpenAiComponent::class,
            'apiKey' => 'YOUR_API_KEY',
            'model' => eseperio\openai\responses\enums\OpenAiModel::GPT_4_1_MINI,
            // optional defaults
            'instructions' => 'Always answer politely.',
            'instructionsPolicy' => eseperio\openai\responses\OpenAiComponent::INSTRUCTIONS_COMPLEMENTARY,
        ],
    ],
];
```

`apiKey` and `instructions` are write-only properties to avoid exposing sensitive values.

Available OpenAI models are exposed through the `OpenAiModel` enum for convenient access.

## Usage

### Basic request

```php
$content = Yii::$app->openai->ask('Explain gravity.');
```

### With instructions and metadata

```php
$content = Yii::$app->openai->ask(
    'Explain gravity.',
    'Provide examples',
    ['topic' => 'physics']
);
```

### Handling instructions

`OpenAiComponent` supports a policy for default instructions:

* **required** – user instructions are ignored. If the caller provides instructions, an exception is thrown.
* **complementary** – user instructions are appended to the default ones.
* **optional** – user instructions override the defaults.

### Creating custom requests

To override default configuration for a single call, create an `AskRequest` model:

```php
$request = Yii::$app->openai->createAskRequest();
$request->model = eseperio\\openai\\responses\\enums\\OpenAiModel::GPT_4_1;
$request->input = 'Explain gravity.';
$request->instructions = 'Use simple terms.';

$content = Yii::$app->openai->ask($request);
```

The model is validated before sending the request. If validation fails an exception is thrown.

### Retrieve last response

```php
$response = Yii::$app->openai->getLastResponse();
```

The component returns only the text content of the first item in the response. The full response object can be obtained via `getLastResponse()`.

## Tests

Functional tests are included. Run them with:

```bash
vendor/bin/codecept run functional
```

## License

MIT

