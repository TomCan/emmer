<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\GeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:set-password',
    description: 'Set the password of a user',
)]
class UserPasswordCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $userPasswordHasher,
        private GeneratorService $generatorService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('e-mail', InputArgument::REQUIRED, 'E-mail address of the user')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'The desired password')
            ->addOption('random-password', 'r', InputOption::VALUE_NONE, 'Create a random password')
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
        }

        if ($input->getOption('password')) {
            $password = (string) $input->getOption('password');
            $user->setPassword($this->userPasswordHasher->hashPassword($user, $password));
            $password = 'the supplied password.';
        } elseif ($input->getOption('random-password')) {
            $password = $this->generatorService->generateId(16);
            $user->setPassword($this->userPasswordHasher->hashPassword($user, $password));
        } else {
            $password = $io->askHidden('Enter the password');
            if ('' !== trim($password)) {
                $user->setPassword($this->userPasswordHasher->hashPassword($user, $password));
                $password = 'the supplied password.';
            } else {
                $io->error('Password cannot be empty.');
            }
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('The password has been set to '.$password);

        return Command::SUCCESS;
    }
}
