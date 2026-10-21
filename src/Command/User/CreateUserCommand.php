<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Repository\User\UserRepository;
use App\Service\User\UserFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// The only way to get an account. Whoever can run this owns the server, so the
// address needs no confirmation and nothing about it depends on outgoing mail.
#[AsCommand(
    name: 'app:user:create',
    description: 'Create your DMARCo account',
)]
final class CreateUserCommand extends Command
{
    public const int MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly UserFactory $userFactory,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    public static function validatePassword(?string $value): string
    {
        if (null === $value || mb_strlen($value) < self::MIN_PASSWORD_LENGTH) {
            throw new \RuntimeException(sprintf('Use at least %d characters.', self::MIN_PASSWORD_LENGTH));
        }

        return $value;
    }

    #[\Override]
    protected function configure(): void
    {
        // Earlier installers pass it, so it has to keep being accepted.
        $this->addOption(
            'simple',
            null,
            InputOption::VALUE_NONE,
            'Accepted for compatibility, has no effect',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $io->ask('Email', validator: static function (?string $value): string {
            $email = filter_var($value, FILTER_VALIDATE_EMAIL);
            if (false === $email) {
                throw new \RuntimeException('Enter a valid email address.');
            }

            return $email;
        });
        if ($this->userRepository->findOneBy(['email' => $email])) {
            $io->error('User with email already exists!');

            return Command::FAILURE;
        }
        $name = $io->ask('Name', validator: static function (?string $value): string {
            if (null === $value || '' === trim($value)) {
                throw new \RuntimeException('Enter a name.');
            }

            return trim($value);
        });
        $plainPassword = $io->askHidden('Password', self::validatePassword(...));

        $this->userFactory->create(
            email: $email,
            name: $name,
            plainPassword: $plainPassword,
        );

        $io->success('User created!');

        return Command::SUCCESS;
    }
}
