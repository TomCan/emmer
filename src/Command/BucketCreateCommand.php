<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\BucketService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:bucket:create',
    description: 'Create a new bucket',
)]
class BucketCreateCommand extends Command
{
    public function __construct(
        private BucketService $bucketService,
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Name of the bucket')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'User that owns the bucket')
            ->addOption('no-policies', 'np', InputOption::VALUE_NONE, 'Don\'t create default owner policy')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Override path of the bucket')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');

        if ($input->getOption('user') && $input->getOption('no-policies')) {
            $io->error('You cannot specify both a user and no policies');

            return Command::FAILURE;
        }

        if ($input->getOption('user')) {
            $user = $this->userRepository->findOneBy(['email' => (string) $input->getOption('user')]);
            if (!$user) {
                $io->error('User not found');

                return Command::FAILURE;
            }
        } else {
            $user = null;
        }

        if ($input->getOption('path')) {
            $path = (string) $input->getOption('path');
        } else {
            $path = $name;
        }

        try {
            $bucket = $this->bucketService->createBucket($name, $user, '', $path, !$input->getOption('no-policies'), true);
            $io->success('Bucket created.'.PHP_EOL.$bucket->getIdentifier());

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
