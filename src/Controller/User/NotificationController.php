<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\DTO\Input\User\Notifications;
use App\DTO\Output\User\NotificationsApi;
use App\Entity\User\User;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfonycasts\MicroMapper\MicroMapperInterface;

#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly MicroMapperInterface $microMapper,
    ) {}

    #[OA\Response(
        response: 200,
        description: 'The authenticated user notification settings.',
        content: new Model(type: NotificationsApi::class)
    )]
    #[OA\Get(
        summary: 'Gets the authenticated user notification settings.',
        security: [['Bearer' => []]],
    )]
    #[OA\Tag(name: 'User')]
    #[Route('/v1/user/notifications', name: 'app_notification_get', methods: [Request::METHOD_GET])]
    public function get(
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        return new JsonResponse($this->microMapper->map($user, NotificationsApi::class));
    }

    #[OA\Response(
        response: 200,
        description: 'The authenticated user notification settings.',
        content: new Model(type: NotificationsApi::class)
    )]
    #[OA\Patch(
        summary: 'Updates the authenticated user notification settings.',
        security: [['Bearer' => []]],
    )]
    #[OA\Tag(name: 'User')]
    #[Route('/v1/user/notifications', name: 'app_notification_patch', methods: [Request::METHOD_PATCH])]
    public function patch(
        #[MapRequestPayload]
        Notifications $input,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $user = $this->getManagedUser($user);
        if (null !== $input->unusualNewLoginNotificationEnabled) {
            $user->setUnusualNewLoginNotificationEnabled($input->unusualNewLoginNotificationEnabled);
        }

        if (null !== $input->weeklyOverviewNotificationEnabled) {
            $user->setWeeklyOverviewNotificationEnabled($input->weeklyOverviewNotificationEnabled);
        }

        $this->entityManager->flush();

        return new JsonResponse($this->microMapper->map($user, NotificationsApi::class));
    }

    private function getManagedUser(User $user): User
    {
        $managedUser = $this->userRepository->find($user->getId());
        if (!$managedUser instanceof User) {
            throw new \LogicException('Authenticated user not found.');
        }

        return $managedUser;
    }
}
