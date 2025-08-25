<?php
namespace tests\functional;

use eseperio\openai\responses\enums\OpenAiModel;
use eseperio\openai\responses\OpenAiComponent;
use tests\FunctionalTester;
use Yii;

class OpenAiComponentCest
{
    public function testAskReturnsResponse(FunctionalTester $I): void
    {
        $I->mockOpenAiResponse('mocked');
        $result = Yii::$app->openai->ask('ping');
        $I->assertSame('mocked', $result);
        $I->assertNotNull(Yii::$app->openai->getLastResponse());
    }

    public function testRequiredInstructionsThrows(FunctionalTester $I): void
    {
        /** @var OpenAiComponent $component */
        $component = Yii::$app->openai;
        $component->instructions = 'forced';
        $component->instructionsPolicy = OpenAiComponent::INSTRUCTIONS_REQUIRED;

        try {
            $component->ask('ping', 'custom');
            $I->fail('Exception not thrown');
        } catch (\InvalidArgumentException $e) {
            // expected
        }
    }

    public function testAskWithModel(FunctionalTester $I): void
    {
        $I->mockOpenAiResponse('model based');
        $request = Yii::$app->openai->createAskRequest();
        $request->model = OpenAiModel::GPT_4_1;
        $request->input = 'ping';
        $result = Yii::$app->openai->ask($request);
        $I->assertSame('model based', $result);
    }

    public function testValidationFailureThrows(FunctionalTester $I): void
    {
        $request = Yii::$app->openai->createAskRequest();
        $request->model = '';
        $request->input = 'ping';

        try {
            Yii::$app->openai->ask($request);
            $I->fail('Exception not thrown');
        } catch (\InvalidArgumentException $e) {
            // expected
        }
    }
}
