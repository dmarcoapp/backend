<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\DTO\Input\DMARC\DashboardSettings;
use App\DTO\Output\User\Dashboard as DashboardApi;
use App\Entity\User\User;
use App\Service\User\DashboardMetricsProvider;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly DashboardMetricsProvider $dashboardMetricsProvider,
    ) {}

    #[OA\Get(
        summary: 'Gets the dashboard metrics for the authenticated user.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'The dashboard metrics.',
        content: new Model(type: DashboardApi::class)
    )]
    #[OA\Tag(name: 'User')]
    #[Route('/v1/user/dashboard', name: 'app_user_dashboard', methods: [Request::METHOD_GET])]
    public function get(
        #[CurrentUser]
        User $user,
        #[MapQueryString]
        DashboardSettings $settings,
    ): JsonResponse {
        $userId = $user->getId();
        if (!$userId instanceof Uuid) {
            throw $this->createAccessDeniedException();
        }

        $key = sprintf('dashboard-%s-%d', $userId->toRfc4122(), $settings->periodDays);

        return $this->cache->get($key, function (ItemInterface $item) use ($user, $settings) {
            $item->expiresAt(new \DateTimeImmutable('+5 minutes'));

            return new JsonResponse($this->dashboardMetricsProvider->provide($user, $settings->periodDays));
        });
    }
}
