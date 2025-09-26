<?php

namespace App\Command;

use App\Service\BucketService;
use App\Service\LifecycleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:bucket:lifecycle-process',
    description: 'Process bucket lifecycle',
)]
class BucketLifecycleRunCommand extends Command
{
    public function __construct(private BucketService $bucketService, private LifecycleService $lifecycleService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('bucket', InputArgument::OPTIONAL, 'Bucket name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // get arguments / options
        $bucket = $input->getArgument('bucket');

        // get a specific bucket or all buckets
        if ($bucket) {
            $buckets = [$this->bucketService->getBucket($bucket)];
        } else {
            $buckets = $this->bucketService->getBuckets();
        }

        foreach ($buckets as $bucket) {
            try {
                $this->lifecycleService->processBucketLifecycleRules($bucket);
            } catch (\Exception $e) {
                $io->error('Error processing lifecycle rules for '.$bucket->getName().PHP_EOL.$e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
