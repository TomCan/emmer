<?php

namespace App\Command;

use App\Entity\AccessKey;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\GeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('e-mail', InputArgument::REQUIRED, 'E-mail address of the user to link the access key to')
            ->addOption('label', 'l', InputArgument::OPTIONAL, 'Label for the access key')
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

        $accessKey->setName('EMR'.$this->generatorService->generateId(17, GeneratorService::CLASS_UPPER + GeneratorService::CLASS_NUMBER));
        $accessKey->setSecret(base64_encode(random_bytes(30)));

        $this->entityManager->persist($accessKey);
        $this->entityManager->flush();

        $io->success('A new access key has been generated:'.PHP_EOL.'Access key: '.$accessKey->getName().PHP_EOL.'Secret: '.$accessKey->getSecret());

        return Command::SUCCESS;
    }
}
