<?php

namespace App\Command;

use App\Service\BucketService;
use App\Service\LifecycleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            ->addOption('multi-part-uploads', null, InputOption::VALUE_REQUIRED, 'Delete multipart uploads')
            ->addOption('non-current-versions', null, InputOption::VALUE_REQUIRED, 'Delete non-current versions')
            ->addOption('current-versions', null, InputOption::VALUE_REQUIRED, 'Delete current versions')
            ->addArgument('bucket', InputArgument::OPTIONAL, 'Bucket name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // get arguments / options
        $bucket = $input->getArgument('bucket');
        $mpu = $input->getOption('multi-part-uploads') ?? -1;
        $ncv = $input->getOption('non-current-versions') ?? -1;
        $cv = $input->getOption('current-versions') ?? -1;

        // check if at least one option is specified
        if (-1 == $mpu && -1 == $ncv && -1 == $cv) {
            $io->error('You must specify at least one option');
        }

        // get a specific bucket or all buckets
        if ($bucket) {
            $buckets = [$this->bucketService->getBucket($bucket)];
        } else {
            $buckets = $this->bucketService->getBuckets();
        }

        foreach ($buckets as $bucket) {
            $this->lifecycleService->run($bucket, $mpu, $ncv, $cv, $output);
        }

        return Command::SUCCESS;
    }
}
