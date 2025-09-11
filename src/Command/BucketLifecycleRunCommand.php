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
            ->addArgument('bucket', InputArgument::OPTIONAL, 'Bucket name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $bucket = $input->getArgument('bucket');
        $mpu = $input->getOption('multi-part-uploads');
        $ncv = $input->getOption('non-current-versions');

        if (null == $mpu && null == $ncv) {
            $io->error('You must specify at least one option');
        }

        if ($bucket) {
            $buckets = [$this->bucketService->getBucket($bucket)];
        } else {
            $buckets = [];
        }

        foreach ($buckets as $bucket) {
            $this->lifecycleService->run($bucket, $mpu, $ncv, $output);
        }

        return Command::SUCCESS;
    }
}
