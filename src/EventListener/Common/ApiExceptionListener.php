<?php

declare(strict_types=1);

namespace App\EventListener\Common;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\LockedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: KernelEvents::EXCEPTION, method: 'onKernelException')]
final readonly class ApiExceptionListener
{
    /** @var array<class-string<HttpExceptionInterface>, string> */
    private const array SAFE_MESSAGES = [
        AccessDeniedHttpException::class => 'Access denied',
        BadRequestHttpException::class => 'Bad request',
        ConflictHttpException::class => 'Conflict',
        GoneHttpException::class => 'Gone',
        LengthRequiredHttpException::class => 'Length required',
        LockedHttpException::class => 'Locked',
        MethodNotAllowedHttpException::class => 'Method not allowed',
        NotAcceptableHttpException::class => 'Not acceptable',
        NotFoundHttpException::class => 'Not found',
        PreconditionFailedHttpException::class => 'Precondition failed',
        PreconditionRequiredHttpException::class => 'Precondition required',
        ServiceUnavailableHttpException::class => 'Service unavailable',
        TooManyRequestsHttpException::class => 'Too many requests',
        UnauthorizedHttpException::class => 'Unauthorized',
        UnprocessableEntityHttpException::class => 'Unprocessable entity',
        UnsupportedMediaTypeHttpException::class => 'Unsupported media type',
    ];

    public function __construct(
        #[Autowire(env: 'APP_ENV')]
        private string $env,
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        if ('dev' === $this->env) {
            return;
        }

        $exception = $event->getThrowable();
        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;
        $message = $exception instanceof HttpExceptionInterface
            ? $this->getSafeHttpMessage($exception)
            : 'Internal server error';

        $response = [
            'error' => [
                'code' => $statusCode,
                'details' => [],
                'message' => $message,
            ],
            'success' => false,
        ];

        $previous = $exception->getPrevious();
        if ($previous instanceof ValidationFailedException) {
            foreach ($previous->getViolations() as $violation) {
                $response['error']['details'][] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }

            $statusCode = 422;
            $response['error']['code'] = 422;
            $response['error']['message'] = 'Validation error';
        }

        $event->setResponse(new JsonResponse($response, $statusCode));
    }

    private function getSafeHttpMessage(HttpExceptionInterface $exception): string
    {
        $exceptionClass = $exception::class;

        if (!array_key_exists($exceptionClass, self::SAFE_MESSAGES)) {
            return 'An error occurred';
        }

        return self::SAFE_MESSAGES[$exceptionClass];
    }
}
