<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Event\User\EmailVerificationResendEvent;
use App\Repository\User\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'app:user:verification-email:resend',
    description: 'Resend a user email verification',
)]
final class ResendUserEmailVerificationCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $io->ask('Email');
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error('User with email does not exist!');

            return Command::FAILURE;
        }

        $this->eventDispatcher->dispatch(new EmailVerificationResendEvent($user));

        $io->success('User verification email resent!');

        return Command::SUCCESS;
    }
}
