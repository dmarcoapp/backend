<?php

declare(strict_types=1);

namespace App\Command\Intake;

use App\Repository\Platform\PlatformSettingsRepository;
use App\Service\Intake\ApiIntakeKeys;
use App\Service\Platform\PlatformSettingsProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:intake:api-key:create',
    description: 'Make a key that uploads reports through the API, and print it once',
)]
final class CreateApiKeyCommand extends Command
{
    public function __construct(
        private readonly PlatformSettingsProvider $settings,
        private readonly PlatformSettingsRepository $settingsRepository,
        private readonly ApiIntakeKeys $keys,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'A name to tell the key by', 'Default')
            ->addOption('enable-api', null, InputOption::VALUE_NONE, 'Switch report intake through the API on as well')
            ->addOption('quiet-key', null, InputOption::VALUE_NONE, 'Print the key alone, for a script to read')
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->settings->isLegacyMode()) {
            $io->error('API keys need APP_ENCRYPTION_KEY. Run the installer again to generate it.');

            return Command::FAILURE;
        }

        $name = trim((string) $input->getArgument('name'));
        if ('' === $name || mb_strlen($name) > 100 || 1 === preg_match('/[\x00-\x1F\x7F]/u', $name)) {
            $io->error('Give the key a name of up to 100 characters.');

            return Command::INVALID;
        }

        if ($input->getOption('enable-api')) {
            $settings = $this->settings->settings();
            $settings->setApiIntakeEnabled(true);
            $this->settingsRepository->save($settings);
        }

        [, $token] = $this->keys->create($name);

        if ($input->getOption('quiet-key')) {
            $output->writeln($token);

            return Command::SUCCESS;
        }

        $io->success(sprintf('Made the API key "%s". It is shown this once:', $name));
        $io->writeln($token);

        return Command::SUCCESS;
    }
}
