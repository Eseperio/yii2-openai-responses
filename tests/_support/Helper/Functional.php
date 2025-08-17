<?php
namespace tests\Helper;

use Codeception\Module;
use Yii;

class Functional extends Module
{
    public function mockOpenAiResponse(string $content): void
    {
        $response = new class($content) {
            public array $output;
            public ?string $outputText;

            public function __construct(string $text)
            {
                $this->outputText = $text;
                $this->output = [
                    [
                        'content' => [
                            ['text' => $text],
                        ],
                    ],
                ];
            }
        };

        $client = new class($response) {
            private $response;

            public function __construct($response)
            {
                $this->response = $response;
            }

            public function responses(): object
            {
                $response = $this->response;
                return new class($response) {
                    private $response;

                    public function __construct($response)
                    {
                        $this->response = $response;
                    }

                    public function create(array $params)
                    {
                        return $this->response;
                    }
                };
            }
        };

        Yii::$app->openai->setClient($client);
    }
}
