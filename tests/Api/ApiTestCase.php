<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

abstract class ApiTestCase extends KernelTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        $_SERVER['APP_ENV'] = 'test';
        $_SERVER['APP_DEBUG'] = '1';
        $_SERVER['DATABASE_URL'] = 'sqlite:///'.\dirname(__DIR__, 2).'/var/test.db';
        $_SERVER['APP_REGISTRATION_ENABLED'] = 'true';
        $_SERVER['APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN'] = 'aggregate-reports.example.test';
        $_ENV['APP_ENV'] = 'test';
        $_ENV['APP_DEBUG'] = '1';
        $_ENV['DATABASE_URL'] = 'sqlite:///'.\dirname(__DIR__, 2).'/var/test.db';
        $_ENV['APP_REGISTRATION_ENABLED'] = 'true';
        $_ENV['APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN'] = 'aggregate-reports.example.test';
        putenv('APP_ENV=test');
        putenv('APP_DEBUG=1');
        putenv('DATABASE_URL=sqlite:///'.\dirname(__DIR__, 2).'/var/test.db');
        putenv('APP_REGISTRATION_ENABLED=true');
        putenv('APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN=aggregate-reports.example.test');

        $options['environment'] = 'test';
        $options['debug'] = true;

        return parent::createKernel($options);
    }

    protected function requestJson(
        string $method,
        string $uri,
        ?array $payload = null,
        array $headers = [],
        ?callable $configureContainer = null,
        bool $catch = true,
    ): Response {
        if (!self::$booted) {
            self::bootKernel();
        }
        $kernel = self::$kernel;
        $container = self::getContainer();

        if (null !== $configureContainer) {
            $configureContainer($container);
        }

        $request = Request::create($uri, $method, content: $payload ? json_encode($payload, JSON_THROW_ON_ERROR) : null);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $kernel->handle($request, catch: $catch);
    }

    protected function mockAcceptedLimiterFactory(): RateLimiterFactoryInterface
    {
        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn(
            new RateLimit(1, new \DateTimeImmutable('+1 hour'), true, 1)
        );

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        return $factory;
    }

    protected function mockRejectedLimiterFactory(): RateLimiterFactoryInterface
    {
        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn(
            new RateLimit(0, new \DateTimeImmutable('+1 hour'), false, 0)
        );

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        return $factory;
    }
}
