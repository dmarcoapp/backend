<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\DTO\Input\User\TwoFactorAppEnable;
use App\DTO\Output\User\TwoFactorApi;
use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;
use App\Repository\User\UserRepository;
use App\Service\User\TwoFactor\TwoFactorService;
use App\Service\User\TwoFactor\TwoFactorStatusMailer;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfonycasts\MicroMapper\MicroMapperInterface;

#[IsGranted('ROLE_USER')]
final class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MicroMapperInterface $microMapper,
        private readonly TwoFactorService $twoFactorService,
        private readonly TwoFactorStatusMailer $twoFactorStatusMailer,
        private readonly UserRepository $userRepository,
    ) {}

    #[OA\Response(
        response: 200,
        description: 'Two-factor authentication details for the authenticated user.',
        content: new Model(type: TwoFactorApi::class)
    )]
    #[OA\Get(
        summary: 'Gets two-factor authentication details for the authenticated user.',
        security: [['Bearer' => []]],
    )]
    #[OA\Tag(name: 'User')]
    #[Route('/v1/user/profile/2fa', name: 'app_profile_2fa_get', methods: [Request::METHOD_GET])]
    public function get(
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        return new JsonResponse($this->microMapper->map($user, TwoFactorApi::class));
    }

    #[OA\Response(
        response: 200,
        description: 'Two-factor authentication app enabled.',
        content: new Model(type: TwoFactorApi::class)
    )]
    #[OA\Post(
        summary: 'Enables app-based two-factor authentication.',
        security: [['Bearer' => []]],
    )]
    #[OA\Tag(name: 'User')]
    #[Route('/v1/user/profile/2fa/app/enable', name: 'app_profile_2fa_app_enable', methods: [Request::METHOD_POST])]
    public function enableTwoFactorApp(
        #[MapRequestPayload]
        TwoFactorAppEnable $input,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $user = $this->getManagedUser($user);
        if (!$this->twoFactorService->isValidAppCode($user, $input->code)) {
            throw new BadRequestHttpException('Invalid two-factor code.');
        }

        $user->setTwoFactorMethod(TwoFactorMethod::APP);
        $this->entityManager->flush();
        $this->twoFactorStatusMailer->sendEnabled($user);

        return new JsonResponse($this->microMapper->map($user, TwoFactorApi::class));
    }

    #[OA\Response(
        response: 200,
        description: 'Two-factor authentication app disabled.',
        content: new Model(type: TwoFactorApi::class)
    )]
    #[OA\Post(
        summary: 'Disables app-based two-factor authentication.',
        security: [['Bearer' => []]],
    )]
    #[OA\Tag(name: 'User')]
    #[Route('/v1/user/profile/2fa/app/disable', name: 'app_profile_2fa_app_disable', methods: [Request::METHOD_POST])]
    public function disableTwoFactorApp(
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $user = $this->getManagedUser($user);
        $user
            ->setTwoFactorMethod(TwoFactorMethod::EMAIL)
            ->setTwoFactorAppSecret(null)
        ;

        $this->entityManager->flush();
        $this->twoFactorStatusMailer->sendDisabled($user);

        return new JsonResponse($this->microMapper->map($user, TwoFactorApi::class));
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
