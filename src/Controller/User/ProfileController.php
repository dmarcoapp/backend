<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\DTO\Input\User\Profile;
use App\DTO\Output\User\UserApi;
use App\Entity\User\User;
use App\Event\User\PasswordChangeEvent;
use App\Repository\User\UserRepository;
use App\Service\User\SessionRevoker;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfonycasts\MicroMapper\MicroMapperInterface;

#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MicroMapperInterface $microMapper,
        private readonly SessionRevoker $sessionRevoker,
    ) {}

    #[OA\Response(
        response: 200,
        description: 'The authenticated user.',
        content: new Model(type: UserApi::class)
    )]
    #[OA\Get(
        summary: 'Gets the authenticated user details.',
        security: [['Bearer' => []]],
    )]
    #[OA\Tag(name: 'User')]
    #[Route('/v1/user/profile', name: 'app_profile_get', methods: [Request::METHOD_GET])]
    public function get(
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        return new JsonResponse($this->microMapper->map($user, UserApi::class));
    }

    #[OA\Response(
        response: 200,
        description: 'The authenticated user.',
        content: new Model(type: UserApi::class)
    )]
    #[OA\Patch(
        description: 'Setting a new password also requires the current one. It signs every session out, so the answer then also holds a new token and refresh_token for this one.',
        summary: 'Updates the authenticated user. Only updates the fields provided.',
        security: [['Bearer' => []]],
    )]
    #[OA\Tag(name: 'User')]
    #[Route('/v1/user/profile', name: 'app_profile_patch', methods: [Request::METHOD_PATCH])]
    public function patch(
        #[MapRequestPayload]
        Profile $input,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $user = $this->getManagedUser($user);
        if (null !== $input->name) {
            $user->setName($input->name);
        }

        if (null !== $input->password) {
            // An access token alone must not be enough to take an account over
            // for good, so a new password costs the current one.
            if (
                null === $input->currentPassword
                || !$this->passwordHasher->isPasswordValid($user, $input->currentPassword)
            ) {
                throw new BadRequestHttpException('Current password does not match.');
            }

            $hashed = $this->passwordHasher->hashPassword($user, $input->password);
            $user->setPassword($hashed);
            $this->eventDispatcher->dispatch(new PasswordChangeEvent($user));
        }

        $this->entityManager->flush();

        $profile = $this->microMapper->map($user, UserApi::class);
        if (null === $input->password) {
            return new JsonResponse($profile);
        }

        // Issued only now, under the password just saved.
        return new JsonResponse([...get_object_vars($profile), ...$this->sessionRevoker->issue($user)]);
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
