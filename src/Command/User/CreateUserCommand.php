<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Repository\User\UserRepository;
use App\Service\User\UserFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a user',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserFactory $userFactory,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $io->ask('Email');
        if ($this->userRepository->findOneBy(['email' => $email])) {
            $io->error('User with email already exists!');

            return Command::FAILURE;
        }
        $name = $io->ask('Name');
        $plainPassword = $io->askHidden('Password');
        $roles = $io->ask('Roles (comma separated list)', default: 'ROLE_USER');

        $this->userFactory->create(
            email: $email,
            name: $name,
            plainPassword: $plainPassword,
            roles: explode(',', $roles),
        );

        $io->success('User created!');

        return Command::SUCCESS;
    }
}
