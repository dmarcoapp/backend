<?php

declare(strict_types=1);

namespace App\Command\Platform;

use App\Repository\Platform\PlatformSettingsRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The installer sets the time zone with it, and prints the one already set.
 */
#[AsCommand(
    name: 'app:platform:timezone',
    description: 'Show or set the time zone the dashboard shows dates and times in',
)]
final class DisplayTimezoneCommand extends Command
{
    public function __construct(
        private readonly PlatformSettingsRepository $repository,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('timezone', InputArgument::OPTIONAL, 'An IANA time zone name, such as Europe/Budapest, or UTC');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->repository->get();
        $requested = $input->getArgument('timezone');

        if (!is_string($requested) || '' === trim($requested)) {
            $output->writeln($settings->getDisplayTimezone() ?? 'UTC');

            return Command::SUCCESS;
        }

        $timezone = self::canonicalName(trim($requested));
        if (null === $timezone) {
            new SymfonyStyle($input, $output)->error(sprintf('%s is not a time zone. Use a name such as Europe/Budapest, or UTC.', $requested));

            return Command::INVALID;
        }

        $this->repository->save($settings->setDisplayTimezone($timezone));
        $output->writeln(sprintf('The dashboard shows dates and times in %s.', $timezone));

        return Command::SUCCESS;
    }

    /**
     * The name as PHP spells it, matched without regard to case.
     */
    private static function canonicalName(string $timezone): ?string
    {
        foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC) as $identifier) {
            if (0 === strcasecmp($identifier, $timezone)) {
                return $identifier;
            }
        }

        return null;
    }
}
