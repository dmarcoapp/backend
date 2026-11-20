<?php

declare(strict_types=1);

namespace App\Service\Platform;

use App\Service\Mailer\PlatformTransport;
use App\Service\Security\UnreadableSecretException;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Sends a test email right away, instead of through the queue the other emails
 * take, so the Platform page can tell whether the mail server accepted it.
 */
final readonly class TestEmailSender
{
    private const int MAX_ERROR_LENGTH = 500;

    public function __construct(
        private PlatformSettingsProvider $settings,
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return null|string why it was not sent, or null when the server took it
     */
    public function send(string $recipient): ?string
    {
        try {
            if (!$this->settings->isMailConfigured()) {
                return 'Outgoing mail is not set up.';
            }

            $email = new TemplatedEmail()
                ->to(new Address($recipient))
                ->subject('DMARCo can send email')
                ->htmlTemplate('emails/platform/test-email.html.twig')
                ->textTemplate('emails/platform/test-email.txt.twig')
            ;

            if (null === new PlatformTransport($this->settings, $this->dispatcher, $this->logger)->send($email)) {
                return 'The mail server did not take the email.';
            }
        } catch (UnreadableSecretException) {
            return 'The stored SMTP password cannot be read with this APP_ENCRYPTION_KEY. Enter it again.';
        } catch (\InvalidArgumentException|TransportExceptionInterface $e) {
            $this->logger->warning('The test email could not be sent.', ['exception' => $e]);

            return mb_substr(trim($e->getMessage()), 0, self::MAX_ERROR_LENGTH);
        }

        return null;
    }
}
