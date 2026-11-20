<?php

declare(strict_types=1);

namespace App\Controller\Intake;

use App\DTO\Input\Intake\MailboxInput;
use App\DTO\Output\Intake\MailboxApi;
use App\DTO\Output\Intake\MailboxLogEntryApi;
use App\DTO\Output\Intake\MailboxTestResultApi;
use App\Entity\Intake\Mailbox;
use App\Entity\User\User;
use App\Message\Intake\PollMailbox;
use App\Repository\Intake\MailboxLogEntryRepository;
use App\Repository\Intake\MailboxRepository;
use App\Service\Intake\MailboxEditor;
use App\Service\Intake\MailboxTester;
use App\Service\Platform\PlatformSettingsProvider;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfonycasts\MicroMapper\MicroMapperInterface;

/**
 * The mailboxes reports are fetched from. Their passwords go in and are never
 * given back.
 */
#[IsGranted(User::ROLE)]
final class MailboxController extends AbstractController
{
    public function __construct(
        private readonly PlatformSettingsProvider $settings,
        private readonly MailboxRepository $mailboxes,
        private readonly MailboxLogEntryRepository $logEntries,
        private readonly MailboxEditor $editor,
        private readonly MailboxTester $tester,
        private readonly MessageBusInterface $messageBus,
        private readonly MicroMapperInterface $microMapper,
    ) {}

    #[OA\Get(summary: 'Lists the mailboxes reports are fetched from.', security: [['Bearer' => []]])]
    #[OA\Response(response: 200, description: 'The mailboxes.', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: MailboxApi::class))))]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/platform/mailboxes', name: 'app_mailbox_list', methods: [Request::METHOD_GET])]
    public function list(): JsonResponse
    {
        $this->assertPlatformMode();

        return new JsonResponse($this->microMapper->mapMultiple($this->mailboxes->findAllByName(), MailboxApi::class));
    }

    #[OA\Post(summary: 'Adds a mailbox.', security: [['Bearer' => []]])]
    #[OA\Response(response: 201, description: 'The mailbox.', content: new Model(type: MailboxApi::class))]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/platform/mailboxes', name: 'app_mailbox_create', methods: [Request::METHOD_POST])]
    public function create(
        #[MapRequestPayload(validationGroups: ['Default', MailboxInput::CREATE])]
        MailboxInput $input,
    ): JsonResponse {
        $this->assertPlatformMode();

        try {
            $mailbox = $this->editor->create($input);
        } catch (\InvalidArgumentException $e) {
            return self::invalid($e->getMessage());
        }

        $this->mailboxes->save($mailbox);

        return new JsonResponse($this->microMapper->map($mailbox, MailboxApi::class), Response::HTTP_CREATED);
    }

    #[OA\Patch(summary: 'Changes a mailbox. A field left out stays as it is.', security: [['Bearer' => []]])]
    #[OA\Response(response: 200, description: 'The mailbox.', content: new Model(type: MailboxApi::class))]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/platform/mailboxes/{mailbox}', name: 'app_mailbox_update', methods: [Request::METHOD_PATCH])]
    public function update(
        Mailbox $mailbox,
        #[MapRequestPayload]
        MailboxInput $input,
    ): JsonResponse {
        $this->assertPlatformMode();

        try {
            $this->editor->update($mailbox, $input);
        } catch (\InvalidArgumentException $e) {
            return self::invalid($e->getMessage());
        }

        $this->mailboxes->save($mailbox);

        return new JsonResponse($this->microMapper->map($mailbox, MailboxApi::class));
    }

    #[OA\Delete(summary: 'Removes a mailbox. The reports fetched from it stay.', security: [['Bearer' => []]])]
    #[OA\Response(response: 204, description: 'Removed.')]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/platform/mailboxes/{mailbox}', name: 'app_mailbox_delete', methods: [Request::METHOD_DELETE])]
    public function delete(Mailbox $mailbox): Response
    {
        $this->assertPlatformMode();
        $this->mailboxes->delete($mailbox);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[OA\Post(summary: 'Signs in to a mailbox and looks at its folders, without reading any message.', security: [['Bearer' => []]])]
    #[OA\Response(response: 200, description: 'Whether it worked, and why not.', content: new Model(type: MailboxTestResultApi::class))]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/platform/mailboxes/{mailbox}/test', name: 'app_mailbox_test', methods: [Request::METHOD_POST])]
    public function test(Mailbox $mailbox): JsonResponse
    {
        $this->assertPlatformMode();
        $result = $this->tester->test($mailbox);

        return new JsonResponse(new MailboxTestResultApi($result->ok, $result->error, $result->messages, $result->folders));
    }

    #[OA\Post(summary: 'Reads the mailbox now, instead of at its next turn.', security: [['Bearer' => []]])]
    #[OA\Response(response: 202, description: 'Queued.')]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/platform/mailboxes/{mailbox}/poll', name: 'app_mailbox_poll', methods: [Request::METHOD_POST])]
    public function poll(Mailbox $mailbox): JsonResponse
    {
        $this->assertPlatformMode();
        $this->messageBus->dispatch(new PollMailbox($mailbox->getId()->toRfc4122()));

        return new JsonResponse(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }

    #[OA\Get(summary: 'What happened when the mailbox was last read, newest first.', security: [['Bearer' => []]])]
    #[OA\Response(response: 200, description: 'The latest entries.', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: MailboxLogEntryApi::class))))]
    #[OA\Tag(name: 'Intake')]
    #[Route('/v1/platform/mailboxes/{mailbox}/log', name: 'app_mailbox_log', methods: [Request::METHOD_GET])]
    public function log(Mailbox $mailbox): JsonResponse
    {
        $this->assertPlatformMode();

        $entries = [];
        foreach ($this->logEntries->findLatest($mailbox) as $entry) {
            $entries[] = new MailboxLogEntryApi($entry->getLevel(), $entry->getMessage(), $entry->getContext(), $entry->getCreatedAt());
        }

        return new JsonResponse($entries);
    }

    /**
     * Mailbox passwords are encrypted, so mailboxes wait for the key.
     */
    private function assertPlatformMode(): void
    {
        if ($this->settings->isLegacyMode()) {
            throw new ConflictHttpException('Mailboxes need APP_ENCRYPTION_KEY, which the installer generates.');
        }
    }

    /**
     * In the shape of every other refused change.
     */
    private static function invalid(string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'details' => [['field' => '', 'message' => $message]],
                'message' => 'Validation error',
            ],
            'success' => false,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
