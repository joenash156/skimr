<?php

declare(strict_types=1);

namespace App\Auth\Console;

use App\Auth\AuthLinks;
use App\Auth\InviteService;
use App\Auth\Token\TokenType;
use App\Auth\Token\UserTokenService;
use App\Users\User;
use App\Users\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates the first super admin (P1 brief B1 task 17):
 *
 *     php bin/console app:create-super-admin --email=you@example.com --first=Ama --last=Mensah
 *
 * The account is created as role=super_admin, status=invited, and a one-time
 * link (valid 24 hours) is printed. Opening it sets the password through
 * POST /auth/invite/accept. No password ever passes through the terminal.
 */
#[AsCommand(name: 'app:create-super-admin', description: 'Create an invited super admin and print a one-time setup link')]
final class CreateSuperAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserTokenService $tokens,
        private readonly AuthLinks $links,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email address of the new super admin')
            ->addOption('first', null, InputOption::VALUE_REQUIRED, 'First name')
            ->addOption('last', null, InputOption::VALUE_REQUIRED, 'Last name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = mb_strtolower(trim((string) $input->getOption('email')));
        $first = trim((string) $input->getOption('first'));
        $last = trim((string) $input->getOption('last'));

        $errors = [];
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 254) {
            $errors[] = '--email must be a valid email address.';
        }
        if ($first === '' || mb_strlen($first) > 80) {
            $errors[] = '--first is required (at most 80 characters).';
        }
        if ($last === '' || mb_strlen($last) > 80) {
            $errors[] = '--last is required (at most 80 characters).';
        }
        if ($errors === [] && $this->users->emailExists($email)) {
            $errors[] = 'An account with that email already exists.';
        }
        if ($errors !== []) {
            foreach ($errors as $error) {
                $output->writeln('<error>' . $error . '</error>');
            }

            return Command::INVALID;
        }

        $user = $this->users->create([
            'email' => $email,
            'first_name' => $first,
            'last_name' => $last,
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_INVITED,
        ]);
        $token = $this->tokens->issue($user->id, TokenType::ADMIN_INVITE, InviteService::TTL);

        $output->writeln(sprintf('<info>Super admin %s created (status: invited).</info>', $email));
        $output->writeln('Open this link within 24 hours to set the password. It works once; don\'t share it:');
        $output->writeln($this->links->acceptInvite($token));

        return Command::SUCCESS;
    }
}
