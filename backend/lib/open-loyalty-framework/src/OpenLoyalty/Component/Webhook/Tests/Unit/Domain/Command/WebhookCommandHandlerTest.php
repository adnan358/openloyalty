<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Component\Webhook\Tests\Unit\Domain\Command;

use OpenLoyalty\Component\Webhook\Domain\Command\DispatchWebhook;
use OpenLoyalty\Component\Webhook\Domain\Command\WebhookCommandHandler;
use OpenLoyalty\Component\Webhook\Infrastructure\Client\WebhookClient;
use OpenLoyalty\Component\Webhook\Infrastructure\WebhookConfigProvider;

/**
 * Class WebhookCommandHandlerTest.
 */
class WebhookCommandHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_post_webhook_when_webhooks_is_enabled()
    {
        $clientMock = $this->getMockBuilder(WebhookClient::class)->getMock();
        $configProviderMock = $this->getMockBuilder(WebhookConfigProvider::class)->getMock();

        $configProviderMock->method('isEnabled')->willReturn(true);
        $clientMock->expects($this->exactly(1))->method('postAction');

        $handler = new WebhookCommandHandler($clientMock, $configProviderMock);
        $command = new DispatchWebhook('type', []);
        $handler->handleDispatchWebhook($command);
    }

    /**
     * @test
     */
    public function it_post_webhook_when_webhooks_is_disabled()
    {
        $clientMock = $this->getMockBuilder(WebhookClient::class)->getMock();
        $configProviderMock = $this->getMockBuilder(WebhookConfigProvider::class)->getMock();

        $configProviderMock->method('isEnabled')->willReturn(false);
        $clientMock->expects($this->never())->method('postAction');

        $handler = new WebhookCommandHandler($clientMock, $configProviderMock);
        $command = new DispatchWebhook('type', []);
        $handler->handleDispatchWebhook($command);
    }

    /**
     * @test
     */
    public function it_post_webhook_with_config_provider_uri()
    {
        $clientMock = $this->getMockBuilder(WebhookClient::class)->getMock();
        $configProviderMock = $this->getMockBuilder(WebhookConfigProvider::class)->getMock();

        $configProviderMock
            ->method('isEnabled')->willReturn(true);

        $configProviderMock
            ->method('getUri')->willReturn('https://example.com');

        $clientMock->expects($this->exactly(1))->method('postAction');
        $clientMock->method('postAction')->with('https://example.com');

        $handler = new WebhookCommandHandler($clientMock, $configProviderMock);
        $command = new DispatchWebhook('type', []);
        $handler->handleDispatchWebhook($command);
    }
}
