<?php

namespace App\Command;

use App\Entity\AccessKey;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EncryptionService;
use App\Service\GeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:create-access-key',
    description: 'Create a new access key for a user',
)]
class AccessKeyCreateCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private GeneratorService $generatorService,
        private EncryptionService $encryptionService,
        private string $encryptionKey,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('e-mail', InputArgument::REQUIRED, 'E-mail address of the user to link the access key to')
            ->addOption('label', 'l', InputOption::VALUE_REQUIRED, 'Label for the access key')
            ->addOption('access-key', null, InputOption::VALUE_REQUIRED, 'Use this Access key instead of generating a random')
            ->addOption('access-secret', null, InputOption::VALUE_REQUIRED, 'Use this Access key secret instead of generating a random one')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('e-mail');

        // check if user already exists
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error('User not found');

            return Command::FAILURE;
        }

        $accessKey = new AccessKey();
        $accessKey->setUser($user);

        if ($input->getOption('label')) {
            $accessKey->setLabel((string) $input->getOption('label'));
        } else {
            $accessKey->setLabel('Generated access key');
        }

        if ($input->getOption('access-key')) {
            $accessKey->setName((string) $input->getOption('access-key'));
        } else {
            $accessKey->setName('EMR'.$this->generatorService->generateId(17, GeneratorService::CLASS_UPPER + GeneratorService::CLASS_NUMBER));
        }
        if ($input->getOption('access-secret')) {
            $accessKey->setSecret($this->encryptionService->encryptString((string) $input->getOption('access-secret'), $this->encryptionKey, raw: false));
        } else {
            $accessKey->setSecret($this->encryptionService->encryptString(base64_encode(random_bytes(30)), $this->encryptionKey, raw: false));
        }

        $this->entityManager->persist($accessKey);
        $this->entityManager->flush();

        $io->success('A new access key has been generated:'.PHP_EOL.'Access key: '.$accessKey->getName().PHP_EOL.'Secret: '.$this->encryptionService->decryptString($accessKey->getSecret(), $this->encryptionKey, raw: false));

        return Command::SUCCESS;
    }
}
