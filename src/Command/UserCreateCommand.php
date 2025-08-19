<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a new user',
)]
class UserCreateCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('e-mail', InputArgument::REQUIRED, 'E-mail address of the user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('e-mail');

        // check if user already exists
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user) {
            $io->error('User already exists');

            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword('');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('User created. You can set a password with app:user:set-password');

        return Command::SUCCESS;
    }
}
