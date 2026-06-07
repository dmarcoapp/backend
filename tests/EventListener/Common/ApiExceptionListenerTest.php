<?php

declare(strict_types=1);

namespace App\Tests\EventListener\Common;

use App\EventListener\Common\ApiExceptionListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * @internal
 */
#[CoversClass(ApiExceptionListener::class)]
final class ApiExceptionListenerTest extends TestCase
{
    public function testDoesNothingInDev(): void
    {
        $listener = new ApiExceptionListener('dev');
        $event = $this->createExceptionEvent(new \RuntimeException('boom'));

        $listener->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    public function testRegistersAsKernelExceptionListener(): void
    {
        $attributes = (new \ReflectionClass(ApiExceptionListener::class))
            ->getAttributes(AsEventListener::class)
        ;

        self::assertCount(1, $attributes);

        $arguments = $attributes[0]->getArguments();

        self::assertSame(KernelEvents::EXCEPTION, $arguments['event']);
        self::assertSame('onKernelException', $arguments['method']);
    }

    public function testMapsSafeHttpExceptionMessage(): void
    {
        $listener = new ApiExceptionListener('prod');
        $event = $this->createExceptionEvent(new BadRequestHttpException('bad'));

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Bad request', $payload['error']['message']);
        self::assertSame(400, $payload['error']['code']);
    }

    public function testFormatsValidationErrors(): void
    {
        $listener = new ApiExceptionListener('prod');
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                message: 'Invalid value',
                messageTemplate: null,
                parameters: [],
                root: null,
                propertyPath: 'email',
                invalidValue: 'bad',
            ),
        ]);
        $previous = new ValidationFailedException('payload', $violations);
        $exception = new BadRequestHttpException('bad', $previous);
        $event = $this->createExceptionEvent($exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(422, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Validation error', $payload['error']['message']);
        self::assertSame(422, $payload['error']['code']);
        self::assertSame('email', $payload['error']['details'][0]['field']);
    }

    public function testNonHttpExceptionsReturn500(): void
    {
        $listener = new ApiExceptionListener('prod');
        $event = $this->createExceptionEvent(new \RuntimeException('boom'));

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(500, $response->getStatusCode());
    }

    public function testUnknownHttpExceptionUsesGenericMessage(): void
    {
        $listener = new ApiExceptionListener('prod');
        $exception = new class(418) extends \RuntimeException implements HttpExceptionInterface {
            public function __construct(private int $status)
            {
                parent::__construct('teapot');
            }

            public function getStatusCode(): int
            {
                return $this->status;
            }

            public function getHeaders(): array
            {
                return [];
            }
        };
        $event = $this->createExceptionEvent($exception);

        $listener->onKernelException($event);

        $payload = json_decode((string) $event->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('An error occurred', $payload['error']['message']);
    }

    private function createExceptionEvent(\Throwable $exception): ExceptionEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new ExceptionEvent(
            $kernel,
            Request::create('/test'),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
