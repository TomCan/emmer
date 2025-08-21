<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\BucketService;
use App\Service\PolicyService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:policy:create',
    description: 'Create a new policy and attach it to a user or a bucket',
)]
class PolicyCreateCommand extends Command
{
    public function __construct(
        private PolicyService $policyService,
        private UserRepository $userRepository,
        private BucketService $bucketService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Name of the policy')
            ->addOption('policy', 'p', InputArgument::OPTIONAL, 'Policy object as string')
            ->addOption('file', 'f', InputArgument::OPTIONAL, 'Read policy from file')
            ->addOption('bucket', 'b', InputArgument::OPTIONAL, 'Bucket to attach the policy to')
            ->addOption('user', 'u', InputArgument::OPTIONAL, 'User to attach the policy to')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('policy') && !$input->getOption('file')) {
            $io->error('You must provide either a policy or a file');

            return Command::FAILURE;
        }

        if ($input->getOption('policy')) {
            $policyString = (string) $input->getOption('policy');
        } else {
            if (!file_exists($input->getOption('file')) || !is_readable($input->getOption('file'))) {
                $io->error('File cannot be read');

                return Command::FAILURE;
            } else {
                $policyString = file_get_contents($input->getOption('file'));
            }
        }

        if ($input->hasOption('bucket')) {
            $bucket = $this->bucketService->getBucket((string) $input->getOption('bucket'));
        } else {
            $bucket = null;
        }

        if ($input->hasOption('user')) {
            $user = $this->userRepository->findOneBy(['email' => (string) $input->getOption('user')]);
        } else {
            $user = null;
        }

        try {
            $this->policyService->createPolicy($input->getArgument('name'), $policyString, $user, $bucket, true);
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
