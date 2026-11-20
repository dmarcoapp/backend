<?php

declare(strict_types=1);

namespace App\Command\Platform;

use App\Service\Platform\EnvironmentImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs on every start, after the migrations, and the installer runs it to
 * learn which lines of .env it may remove.
 */
#[AsCommand(
    name: 'app:platform:import-env',
    description: 'Take the settings that used to be environment variables over into the database',
)]
final class ImportEnvironmentCommand extends Command
{
    public function __construct(
        private readonly EnvironmentImporter $importer,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format, text or json', 'text');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->importer->import();

        if ('json' === $input->getOption('format')) {
            $output->writeln(json_encode([
                'imported' => $result->imported,
                'invalid' => $result->invalid,
                'kept' => $result->kept,
                'mode' => $result->legacyMode ? 'legacy' : 'platform',
                'settled' => $result->settled(),
            ], JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);

        if ($result->legacyMode) {
            $io->note('APP_ENCRYPTION_KEY is not set, so the settings keep coming from the environment. Run the installer again to finish the upgrade.');

            return Command::SUCCESS;
        }

        foreach ($result->imported as $name) {
            $io->writeln(sprintf('Took %s over into the database.', $name));
        }
        foreach ($result->invalid as $name) {
            $io->warning(sprintf('%s is set to something DMARCo cannot take over. Set it on the Platform page instead.', $name));
        }
        if ([] !== $result->settled()) {
            $io->writeln(sprintf('The database now answers for %s. Those lines can go from .env.', implode(', ', $result->settled())));
        }

        return Command::SUCCESS;
    }
}
