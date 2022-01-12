<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FilesExternalMigrate\Command;

use OC\Core\Command\Base;
use OCA\Files_External\Service\GlobalStoragesService;
use OCA\Files_External\Service\UserStoragesService;
use OCP\Files\Storage\IStorage;
use OCP\IDBConnection;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Migrate extends Base {
	protected $globalService;
	protected $userService;
	protected $connection;

	public function __construct(GlobalStoragesService $globalService, UserStoragesService $userService, IDBConnection $connection) {
		parent::__construct();
		$this->globalService = $globalService;
		$this->userService = $userService;
		$this->connection = $connection;
	}

	protected function configure() {
		$this
			->setName('files_external_migrate:migrate')
			->setDescription('Delete an external mount')
			->addArgument(
				'mount_id',
				InputArgument::REQUIRED,
				'The id of the mount to edit'
			)->addArgument(
				'options',
				InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
				'The configuration values to set, as "key=value" pairs'
			);
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$mount = $this->globalService->getStorage((int)$input->getArgument('mount_id'));
		$newOptions = array_reduce($input->getArgument('options'), function ($options, $keyValue) {
			[$key, $value] = explode('=', $keyValue);
			$options[$key] = $value;
			return $options;
		}, []);
		$existingConfig = $mount->getBackendOptions();

		$storageClass = $mount->getBackend()->getStorageClass();

		if (!$newOptions) {
			$output->writeln("Current mount configuration:");
			$this->dumpConfig($output, $existingConfig);
		} else {
			$mergedOptions = array_merge($existingConfig, $newOptions);

			/** @var IStorage $newStorage */
			$newStorage = new $storageClass($mergedOptions);
			/** @var IStorage $oldStorage */
			$oldStorage = new $storageClass($existingConfig);

			$newContent = $this->listRoot($newStorage);
			$oldContent = $this->listRoot($oldStorage);

			if ($newContent === $oldContent) {
				$output->writeln("Updating");
				$mount->setBackendOptions($mergedOptions);

				$output->writeln("New configuration:");
				$this->dumpConfig($output, $mergedOptions);

				$helper = $this->getHelper('question');
				$question = new ConfirmationQuestion('Save this configuration? ', false);

				if ($helper->ask($input, $output, $question)) {
					$this->updateStorageId($oldStorage->getId(), $newStorage->getId());
					$this->globalService->updateStorage($mount);
					$output->writeln("Configuration saved");
				}
			} else {
				$output->writeln("<error>New storage configuration does not contain the same files</error>");
			}
		}
	}

	private function dumpConfig(OutputInterface $output, array $config) {
		foreach ($config as $key => $value) {
			$output->writeln("\t$key = $value");
		}
	}

	private function listRoot(IStorage $storage) {
		$dh = $storage->opendir('');
		$content = [];
		while ($file = readdir($dh)) {
			if ($file !== '.' || $file !== '..') {
				$content[] = $file;
			}
		}

		return $content;
	}

	private function updateStorageId($oldId, $newId) {
		$query = $this->connection->getQueryBuilder();

		$query->update('storages', 's')
			->set('id', $query->createNamedParameter($newId))
			->where($query->expr()->eq('id', $query->createNamedParameter($oldId)));
		$query->execute();
	}
}
