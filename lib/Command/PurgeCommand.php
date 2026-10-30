<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Command;

use OCA\Laudatio\AppInfo\Application;
use OCA\Laudatio\BackgroundJob\SyncJob;
use OCP\BackgroundJob\IJobList;
use OCP\Config\IUserConfig;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Notification\IManager as INotificationManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Deletes all data of the app. Nextcloud keeps the tables of an app when it is
 * disabled or removed, and uninstall repair steps would also run on a plain
 * disable (e.g. during a server upgrade), so this command is the explicit way
 * to get rid of the personal data before removing the app.
 */
class PurgeCommand extends Command {
	/** All tables of the app, see lib/Migration */
	private const TABLES = [
		'laudatio_persons',
		'laudatio_groups',
		'laudatio_interested',
		'laudatio_functions',
		'laudatio_slices',
		'laudatio_events',
		'laudatio_notified',
		'laudatio_audit',
	];

	public function __construct(
		private IDBConnection $db,
		private IAppConfig $appConfig,
		private IUserConfig $userConfig,
		private INotificationManager $notificationManager,
		private IJobList $jobList,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this->setName('laudatio:purge')
			->setDescription('Delete all data of Laudatio: persons, groups, functions, events, audit log and settings')
			->setHelp('Drops the database tables of the app and removes its settings, the personal settings of all users, its notifications and its background job. Afterwards the app is disabled: run "occ app:remove laudatio" to remove it completely, or "occ app:enable laudatio" to start over with an empty app.')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation (required when not running interactively)');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ($input->getOption('force') !== true) {
			if (!$input->isInteractive()) {
				$output->writeln('<error>Use --force to purge without confirmation.</error>');
				return self::FAILURE;
			}
			$helper = $this->getHelper('question');
			if (!$helper instanceof QuestionHelper) {
				throw new \LogicException('The question helper is not available.');
			}
			$question = new ConfirmationQuestion('This deletes all persons, groups, functions, events, the audit log and the settings of Laudatio and cannot be undone. Continue? [y/N] ', false);
			if ($helper->ask($input, $output, $question) !== true) {
				$output->writeln('Aborted, nothing was deleted.');
				return self::FAILURE;
			}
		}

		foreach (self::TABLES as $table) {
			$this->db->dropTable($table);
		}
		// Forget the migrations, so enabling the app again creates the tables
		$qb = $this->db->getQueryBuilder();
		$qb->delete('migrations')
			->where($qb->expr()->eq('app', $qb->createNamedParameter(Application::APP_ID)));
		$qb->executeStatement();

		$this->appConfig->deleteApp(Application::APP_ID);
		$this->userConfig->deleteApp(Application::APP_ID);

		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID);
		$this->notificationManager->markProcessed($notification);

		$this->jobList->remove(SyncJob::class);

		$output->writeln('All data of Laudatio was deleted. The app is now disabled: run "occ app:remove laudatio" to remove it, or "occ app:enable laudatio" to start over.');
		return self::SUCCESS;
	}
}
