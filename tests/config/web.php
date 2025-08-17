<?php
return [
    'id' => 'test-app',
    'basePath' => dirname(__DIR__, 2),
    'vendorPath' => dirname(__DIR__, 2).'/vendor',
    'components' => [
        'openai' => [
            'class' => eseperio\openai\responses\OpenAiComponent::class,
            'apiKey' => 'test-key',
            'model' => 'gpt-4.1-mini',
        ],
    ],
];
