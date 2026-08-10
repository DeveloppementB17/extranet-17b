<?php

declare(strict_types=1);

namespace App\Command;

use App\Entreprise\LegacyEntrepriseExchange;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-legacy-entreprises',
    description: 'Importe les clients legacy depuis un JSON ou un dump SQL de l’ancien extranet.',
)]
final class ImportLegacyEntreprisesCommand extends Command
{
    public function __construct(
        private readonly LegacyEntrepriseExchange $exchange,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin vers un .json, .sql ou .sql.gz')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans écrire en base')
            ->addOption('include-archived', null, InputOption::VALUE_NONE, 'Inclut les entreprises archivées');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getArgument('file');
        $dryRun = (bool) $input->getOption('dry-run');
        $includeArchived = (bool) $input->getOption('include-archived');

        if (!is_file($path) || !is_readable($path)) {
            $io->error(sprintf('Fichier introuvable ou illisible : %s', $path));

            return Command::FAILURE;
        }

        $io->title('Import entreprises legacy');
        if ($dryRun) {
            $io->note('Mode dry-run : aucune écriture.');
        }

        try {
            $lower = mb_strtolower($path);
            if (str_ends_with($lower, '.json')) {
                $payload = $this->exchange->decodeJson((string) file_get_contents($path));
            } else {
                $items = $this->exchange->itemsFromSqlDump($path, includeArchived: true);
                $payload = $this->exchange->buildPayload($items, 'sql-dump:'.basename($path));
            }

            $result = $this->exchange->importPayload($payload, includeArchived: $includeArchived, dryRun: $dryRun);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success([
            sprintf('Créées : %d', $result->created),
            sprintf('Mises à jour : %d', $result->updated),
            sprintf('Ignorées : %d', $result->skipped),
        ]);

        if ($result->skippedReasons !== [] && $io->isVerbose()) {
            $io->section('Détail des ignores (extrait)');
            $io->listing(\array_slice($result->skippedReasons, 0, 40));
        }

        if ($result->created > 0 || $result->updated > 0) {
            $io->note('Clients masqués par défaut. Admin → Entreprises → « Afficher anciennes données ».');
        }

        return Command::SUCCESS;
    }
}
