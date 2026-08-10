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
    name: 'app:build-legacy-entreprises-json',
    description: 'Génère un fichier JSON d’import depuis un dump SQL de l’ancien extranet (sans base).',
)]
final class BuildLegacyEntreprisesJsonCommand extends Command
{
    public function __construct(
        private readonly LegacyEntrepriseExchange $exchange,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('dump', InputArgument::REQUIRED, 'Chemin vers le dump .sql ou .sql.gz')
            ->addArgument('output', InputArgument::OPTIONAL, 'Fichier JSON de sortie')
            ->addOption('include-archived', null, InputOption::VALUE_NONE, 'Inclut aussi les entreprises archivées');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dump = (string) $input->getArgument('dump');
        $includeArchived = (bool) $input->getOption('include-archived');
        $outputPath = $input->getArgument('output');

        if (!\is_string($outputPath) || $outputPath === '') {
            $outputPath = dirname(__DIR__, 2).'/var/exports/legacy-entreprises.json';
        }

        try {
            $items = $this->exchange->itemsFromSqlDump($dump, includeArchived: $includeArchived);
            $payload = $this->exchange->buildPayload($items, 'sql-dump:'.basename($dump));
            $json = $this->exchange->encodeJson($payload);

            $dir = dirname($outputPath);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Impossible de créer le dossier %s', $dir));
            }

            if (file_put_contents($outputPath, $json) === false) {
                throw new \RuntimeException(sprintf('Écriture impossible : %s', $outputPath));
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Fichier généré : %s (%d entreprise(s))',
            $outputPath,
            \count($payload['entreprises']),
        ));

        return Command::SUCCESS;
    }
}
