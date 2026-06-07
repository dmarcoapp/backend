<?php

declare(strict_types=1);

namespace App\Command\Email;

use App\Message\Email\ProcessEmail;
use App\Repository\Email\EmailRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:email:process',
    description: 'Dispatch ProcessEmail message for an email',
)]
final class ProcessEmailCommand extends Command
{
    public function __construct(
        private readonly EmailRepository $emailRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'Email UUID');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $id = $input->getArgument('id');
        if (null === $id || '' === trim((string) $id)) {
            $id = $io->ask('Email ID');
        }

        $email = $this->emailRepository->find($id);
        if (!$email) {
            $io->error('Email does not exist!');

            return Command::FAILURE;
        }

        $emailId = $email->getId();
        if (!$emailId instanceof Uuid) {
            $io->error('Email ID is missing.');

            return Command::FAILURE;
        }

        $this->messageBus->dispatch(new ProcessEmail($emailId->toRfc4122()));

        $io->success('ProcessEmail message sent!');

        return Command::SUCCESS;
    }
}
