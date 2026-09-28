<?php

declare(strict_types=1);

namespace App\Command;

use App\Entreprise\LegacyTimeCreditExchange;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-legacy-time-credit-interventions',
    description: 'Complète les interventions manquantes sur les crédits temps legacy déjà importés.',
)]
final class SyncLegacyTimeCreditInterventionsCommand extends Command
{
    public function __construct(
        private readonly LegacyTimeCreditExchange $exchange,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::REQUIRED, 'Dump SQL (.sql/.sql.gz) ou JSON legacy crédits temps')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans écrire en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $source = (string) $input->getArgument('source');
        $dryRun = (bool) $input->getOption('dry-run');

        if (!is_file($source) || !is_readable($source)) {
            $io->error(sprintf('Fichier introuvable : %s', $source));

            return Command::FAILURE;
        }

        $admin = $this->userRepository->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_17B_ADMIN%')
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if ($admin === null) {
            $io->error('Aucun administrateur 17b trouvé pour attribuer les mouvements.');

            return Command::FAILURE;
        }

        try {
            $lower = mb_strtolower($source);
            if (str_ends_with($lower, '.json')) {
                $payload = $this->exchange->decodeJson((string) file_get_contents($source));
            } else {
                $items = $this->exchange->itemsFromSqlDump($source);
                $payload = $this->exchange->buildPayload($items, 'sql-dump:'.basename($source));
            }

            $result = $this->exchange->syncMissingInterventions($payload, $admin, $dryRun);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            '%s%d crédit(s) mis à jour, %d intervention(s) ajoutée(s), %d crédit(s) absents ignorés.',
            $dryRun ? '[dry-run] ' : '',
            $result['creditsTouched'],
            $result['interventionsAdded'],
            $result['skipped'],
        ));

        return Command::SUCCESS;
    }
}
